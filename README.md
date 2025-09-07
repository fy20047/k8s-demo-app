# K8s Demo App 練習紀錄

本專案是一個學習 **Kubernetes (Minikube)** 與 **Docker** 的過程，  
透過一個簡單的 PHP App，練習從 **建置 Docker image → 推送到 Docker Hub → 部署到 K8s** 的完整流程。  

程式碼與設定檔為參考 [Kubernetes 官方範例](https://kubernetes.io/docs/tutorials/) 進行修改與練習。  

---

## 📦 練習環境
- Windows 10 PowerShell  
- [Docker Desktop](https://www.docker.com/products/docker-desktop)  
- [Minikube](https://minikube.sigs.k8s.io/docs/start/)  
- [kubectl](https://kubernetes.io/docs/tasks/tools/)  

---

## 🚀 練習流程

### 1. 啟動 Minikube
```powershell
minikube start --driver=docker
minikube delete --all --purge
docker system prune -f
minikube start --driver=docker
```
檢查環境：
```powershell
kubectl get nodes
kubectl get namespaces
kubectl get pods -A
kubectl get services -A
```

### 2. 準備 Docker image
> 建立 PHP 應用、Dockerfile 與 .dockerignore，建置 image 並推送到 Docker Hub。
#### 2.1 首先在專案根目錄建立檔案
```powershell
 (1).\src\index.php
 (2).\Dockerfile
 (3).\.dockerignore
```

```powershell
docker build -t k8s-demo-app:latest .
docker images
docker run -d -p 8080:80 `
  -e MESSAGE=<MESSAGE> `
  -e USERNAME=<USERNAME> `
  -e PASSWORD=<PASSWORD> `
  --name k8s-demo k8s-demo-app:latest 
```

#### 2.2 接著就可以推送到 Docker Hub
```powershell
docker login
docker build -t <Docker Desktop ID>/k8s-demo-app:latest .
docker push <Docker Desktop ID>/k8s-demo-app:latest
```

### 3. 打造 k8s 內網環境 (ns1)

> 在 ns1 建立 ConfigMap、Secret、Pod、Service、Deployment 與 Ingress。

#### 3.1 首先建立 Namespace
```powershell
kubectl create namespace ns1
kubectl create namespace ns2
kubectl get namespaces
```

#### 3.2 建立 ConfigMap 與 Secret
```powershell
kubectl apply -f cm1.yaml -n ns1
kubectl get configmap -n ns1

kubectl apply -f secret1.yaml -n ns1
kubectl get secret -n ns1
```

#### 3.3 建立 Pod
```powershell
kubectl apply -f po1.yaml -n ns1
kubectl get pods -n ns1
kubectl port-forward pod/po1 8080:80 -n ns1
```

#### 3.4 建立 Pod Service
```powershell
kubectl apply -f po1-svc.yaml -n ns1
kubectl get services -n ns1
kubectl port-forward service/po1-svc 8080:80 -n ns1
```

#### 3.5 建立 Deployment 
```powershell
kubectl apply -f deploy1.yaml -n ns1
kubectl get deployments -n ns1
kubectl get pods -n ns1   # replicas: 3 → 會有三個 Pod
kubectl port-forward deployment/deploy1 8080:80 -n ns1
```

#### 3.6 建立 Deployment Service
```powershell
kubectl apply -f deploy1-svc.yaml -n ns1
kubectl get services -n ns1
kubectl port-forward service/deploy1-svc 8080:80 -n ns1
```
> 到這邊 Pod 以及 Deployment 和相對應的 Service 都部署建立完成後，Cluster 內網的相互溝通大致上就沒問題了！

---

#### 注意：更新與 Rollout
- 若本機先前有使用 `docker run -p 8080:80 ...` 起過容器，會佔用 8080 port。  
- 這時候即使 K8s Pod 已經成功啟動並 port-forward 到 8080，瀏覽器仍會連到本機 Docker 容器，而不是 K8s Pod。  
- 解決方式：  
  1. 先停掉本機容器：`docker stop <容器名稱>`  
  2. 或改用其他本機 port，例如 `kubectl port-forward deployment/deploy1 8888:80 -n ns1`
```powershell
docker stop k8s-demo # 先停掉本機 Docker 容器
kubectl apply -f deploy1.yaml -n ns1 # 重新套用 Deployment
kubectl rollout status deployment/deploy1 -n ns1 # 查看更新狀態
kubectl port-forward deployment/deploy1 8888:80 -n ns1 # 改用不同的本機 port 測試
```

---

### ４. 打通 k8s 內網與外部的連線
> 在這邊要建立 Ingress 路由規則，但在這之前要先確定 Cluster 內部有跑著 Ingress Controller（本練習使用 NGINX Ingress）。

#### 4.1 啟用 Ingress Controller（Minikube）
```powershell
minikube addons enable ingress
kubectl get pods -n ingress-nginx
# 確認 ingress-nginx-controller 為 Running
```

#### 4.2 本機 domain 綁定（/etc/hosts）
> 為了用自訂網域測試（例如 k8s.test.com），把它指到本機 127.0.0.1。
```powershell
Add-Content -Path "C:\Windows\System32\drivers\etc\hosts" -Value "127.0.0.1 k8s.test.com"
# 驗證
ping k8s.test.com
```

#### 4.3 開啟 tunnel（讓本機能連進 Minikube）
```powershell
minikube tunnel # 需保持開啟，否則無法由本機存取 Ingress
```

#### 4.4 建立 Ingress 路由（以 Host + Path 導流到不同 Service）。
```powershell
kubectl apply -f ing1.yaml -n ns1
kubectl get ingress -n ns1
```

#### 4.5 測試（瀏覽器或 curl）
```text
http://k8s.test.com/            # 進 popo（根路徑）
http://k8s.test.com/po1         # 進 po1
http://k8s.test.com/deploy1     # 進 deploy1
```
> 也可以測 Service 互叫（在瀏覽器加上如 `?url=http://po1-svc`，由前端 Pod 再去呼叫 cluster 內的 Service）：
```text
http://k8s.test.com/po1?url=http://deploy1-svc
http://k8s.test.com/deploy1?url=http://po1-svc
```
> 在這邊第一段會顯示「目前處理請求的 Pod」，下面則會印出「被呼叫的 svc 回應」。
---
#### 補充說明：Service 呼叫的兩種情境
#### 一、 正常情境：瀏覽器直接打 Pod/Service
- 使用者（外部 client）透過 ingress → service → pod 直接存取應用程式。
 - Ingress 根據 Host/Path 把流量導到對應的 Service，Service 再把 request 送進 Pod。
 - 這時候流量的來源是 `外部使用者 → Ingress → Service → Pod`。

#### 二、 Pod 自己當前端，再去呼叫 cluster 內的 Service
 有時候使用者不是直接打某個 Service，而是 Pod 裡的程式碼需要再去呼叫其他 Service。
 例如在 `index.php` 裡有一行：
 ```php
 echo file_get_contents("http://po1-svc");
 ```
 代表：
 - 這個 PHP Pod 自己當 client，在 cluster 內網去連 `po1-svc` 。
 - Kubernetes DNS 會解析 `po1-svc` 成為 Service 的 ClusterIP，流量再由 Service 導到對應的 Pod。
 - 最後結果回傳到 PHP Pod，然後顯示在網頁上。
 
 所以 `http://k8s.test.com/deploy1?url=http://po1-svc` 的流程就是：
  ```scss
 Request
 → Ingress (根據 Host/Path 判斷，導到 deploy1-svc)
   → Service (deploy1-svc)
     → 前端 Pod (deploy1) 接收請求，執行 index.php
       → 發現帶有 ?url=http://po1-svc
       → 在 Pod 內再發一個內部 HTTP request
         → Service (po1-svc)
           → 後端 Pod (po1) 執行並回應
       ← 回傳給前端 Pod (deploy1)
 ← 最後前端 Pod 把「自己的輸出 + 後端 Pod 的輸出」一起回傳給瀏覽器
 ```
> Pod 裡的程式碼自己發 HTTP request 去連 Kubernetes Service（透過內網 DNS 解析），而不是使用者直接打 Pod。

#### 三、 練習的原因
 我認為這正是微服務架構的核心概念：前端 Pod（例如 Web server, API Gateway）通常不直接存取資料，而是透過呼叫後端 Service。
 這樣的好處是：
  - 不同 Service 可以獨立部署、獨立維護。
  - Service 名稱（如 `po1-svc`）就是 cluster 內的 DNS 名稱，不用擔心 Pod IP 變動。
  - Ingress 只需要對外公開前端 Service，內部 Service 可以繼續保護在 cluster 內。

---

### 5. 在 ns2 建立相同資源並操作
> 在第二個 namespace `ns2`，重複建立 ConfigMap、Secret、Pod、Service、Deployment 與 Ingress：
```powershell
kubectl apply -f cm2.yaml -n ns2
kubectl apply -f secret2.yaml -n ns2
kubectl apply -f po2.yaml -n ns2
kubectl apply -f po2-svc.yaml -n ns2
kubectl apply -f deploy2.yaml -n ns2
kubectl apply -f deploy2-svc.yaml -n ns2
kubectl apply -f ing2.yaml -n ns2
```

#### 5.1 跨 namespace 測試
- 失敗案例（跨 namespace，無法解析）：　` http://k8s.test.com/po1?url=http://po2-svc `
 > 因為 `po2-svc` 在 ns2，而 `po1` Pod 在 ns1，K8s DNS 預設會嘗試解析成 `po2-svc.ns1.svc.cluster.local`，進而失敗。
- 成功案例（指定 namespace）：　` http://k8s.test.com/po1?url=http://po2-svc.svc `
 > 加上 namespace `ns2`，K8s DNS 就能正確解析成 `po2-svc.ns2.svc.cluster.local`。
- 完整 FQDN（K8s 內部 DNS 規則）：　` http://k8s.test.com/po1?url=http://po2-svc.svc.cluster.local `
 > 這是最完整的寫法，明確指出 service、namespace 與 cluster domain。

> 所以!! 經過上面的練習測試，可以整理 K8s DNS 規則如下：
- 同 namespace：` http://<service-name> `
- 跨 namespace：` http://<service-name>.<namespace> `
- 完整格式：` http://<service-name>.<namespace>.svc.cluster.local `

---
#### 觀念釐清：Ingress Controller 與 minikube tunnel
- Ingress Controller (cluster-wide)：cluster 的為一入口，負責整合整個 cluster 所有 namespace 的 ingress 規則，這邊的練習流程是使用 Nginx Ingress Controller。
- Ingress (namespace scoped)：只能指向同 namespace 的 Service；因此每個 namespace 需要自己的 Ingress。
- Service：負責將流量導向正確的 Pod。
- minikube tunnel：與 Ingress Controller 不同，而是幫 type=LoadBalancer 的 Service 分配一個本機可用的外部 IP，模擬雲端 LoadBalancer。
- 也就是說 Namespace 雖然提供資源隔離的效果，但 Ingress Controller 是全域的，會把所有 Ingress 的 host/path 規則整合進單一入口。
---

### 6. Helm 實作流程
> 隨著系統越來越複雜，所牽扯到的 Kubernetes 元件（Pod、Service、Deployment、Ingress…）會變得難以維護。  
> 如果每次都要用 `kubectl apply` 去一個個建立，流程繁瑣且容易出錯。  
> 因此學習完一輪基礎的 k8s 後，接下來換學習使用 Helm 這個 package 管理系統，來將整組的元件包裝成一個 package（Chart，航海圖）。  
> 這意味著我們先定義好航海路線，舵手只要依照航海圖去執行，就能快速抵達目的地。  
> 這邊會簡單跑一輪如何使用 Helm 將一組 Kubernetes 資源（Deployment、Service、Ingress…）打包成 Chart，並透過 Helm 的版本控制機制來進行升級、回滾與移除。

#### 6.1 安裝環境
```powershell
choco install kubernetes-helm # 安裝 Helm
helm version # 確認版本
```

#### 6.2 建立 Helm Chart
> 透過下方指令，helm 會自動生成預設好的 Chart。
```powershell
helm create demo-chart 
```

#### 6.3 修改 Chart template
> 接下來就可以依照需求來調整，我這邊將原始 template 的內容全數刪除，然後把在 k8s 中屬於同一個 namespace 的元件複製出來。  
> 接著去刪掉檔名與 YAML 內容裡的數字 ID，例如將 `deploy1` 改為 `deploy{{ .Values.nsId }}`。  
> 並將共用的參數抽取出來放到 `values.yaml` 中
```yaml
nsId: 1 # ID
replicaCount: 3 # deployment 部署的 pod 數量
```
> 接著建立另一份 `values-ns2.yaml` 覆蓋檔，這邊只修改需要的值：
```yaml
nsId: 2
```
> 修改好後，後續就可以用同一套模板部署不同 namespace !

#### 6.4 準備 Namespace
> 這邊為了練習方便，我選擇將過去的 `ns1` 和 `ns2` 刪除並重建，以確保乾淨。
```powershell
kubectl delete namespace ns1
kubectl delete namespace ns2
kubectl create namespace ns1
kubectl create namespace ns2
```

#### 6.5 預覽渲染結果
> 在設定好並且實際安裝 chart 之前，可以先使用下方指令來在終端預覽結果，確定內容沒問題後再去進行安裝。  
> 例如去檢查 po{{ .Values.nsId }} 是否正確被替換為 `po1` 或 `po2`。
```powershell
helm template . | more
helm template . --values=values-ns2.yaml | more
--dry-run --debug # 預先驗證
```

#### 6.6 安裝 Chart
> 預覽後確定沒問題，接下來就可以進行 chart 安裝。
```powershell
helm install <release-name> <chart> -n <namespace>
helm install chart1 . -n ns1 # 安裝到 ns1
helm install chart2 . -n ns2 --values=values-ns2.yaml # 安裝到 ns2（並且用 values-ns2.yaml 覆蓋）
```
> 在此處也可以使用以下指令來確保 Deployment、Pod、Service 等元件是否都正常建立。
```powershell
kubectl get all -n ns1
kubectl get all -n ns2
```

#### 6.7 對外連線
最後就是啟動 Minikube tunnel（模擬 LoadBalancer）來驗收啦!
```powershell
minikube tunnel # 保持此視窗開啟
```

### 7. Helm Release Life Cycle
> Helm 除了提供打包成 Helm chart 一鍵安裝的功能之外，也提供版本控制的機制。

#### 7.1 升級
> 建立好 chart 後若修改內容，可以使用如下方的指令，來根據更新後的 template 或 values，產生新 revision。
```powershell
helm upgrade <release-name> . -n <namespace>
```

#### 7.2 查看歷史
> 用來顯示安裝、升級、回滾的版本紀錄。
```powershell
helm history <release-name> -n <namespace>
```

#### 7.3 Rollback
> Rollback 除了還原版本，也會產生新的 revision，從 helm history 可看到完整紀錄。
```powershell
helm rollback chart1 -n ns1 # 回到上一個版本
helm rollback chart1 2 -n ns1 # 回到指定版本 (如 revision 2)
```

#### 7.4 移除 Release
> 刪除 Helm 管理的資源，但 namespace ns1 保留，這樣就不用透過 kubectl delete ns 清空重來。
```powershell
helm uninstall chart1 -n ns1
```

#### 7.5 重置整個 Cluster
> 若練習結束，想回到最初狀態：
```powershell
minikube stop
minikube delete
```
### 結論
這次的實作練習，讓我對 Kubernetes 的核心運作模式有了實際體驗與理解：
- Namespace 與資源隔離：知道如何在不同 namespace 部署相同資源，並用 K8s DNS (`<svc>.<ns>.svc.cluster.local`) 在跨 namespace 之間正確溝通。
- Service 與 Pod 關係：理解 Service 的 ClusterIP 與 DNS 解析，確保 Pod 即使 IP 改變，仍能透過 Service 穩定存取。
- Ingress 與 Ingress Controller：能清楚區分 Ingress（namespace 資源）與 Ingress Controller（cluster-wide），並透過 minikube tunnel 模擬雲端 LoadBalancer，打通外部流量。
在這些基礎之上，也額外透過 Helm 學到如何把多個資源打包成 Chart，並利用 install / upgrade / rollback / uninstall 進行版本控制，讓整組資源的部署與維護更有效率!
