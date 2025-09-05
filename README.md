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

### 3. 打造 k8s 內網環境

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

#### 補充：
1. 正常情境：瀏覽器直接打 Pod/Service
- 平常在瀏覽器輸入網址（透過 ingress → service → pod），是使用者（外部 client）直接打 cluster 內的 Service/Pod。
 - Ingress 會根據 host/path 把 request 導到對應的 Service，Service 再把流量送進 Pod。
 - 這時候流量的來源是「外部使用者 → Ingress → Service → Pod」。
--- 
2. 另一種情境：Pod 自己當「前端」，再去呼叫 cluster 內的 Service
 有時候使用者不是從外部直接打 Service，而是某個 Pod（通常是前端 App Pod）需要在程式裡呼叫 cluster 內的其他 Service。
 例如在 `index.php` 裡有一行
 ```php
 echo file_get_contents("http://po1-svc");
 ```
 這代表：
 - 這個 PHP Pod 自己當 client，在 cluster 內網去打 po1-svc 這個 Service。
 - Kubernetes DNS 會解析 po1-svc 成為 Service 的 ClusterIP，流量再由 Service 導到對應的 Pod。
 - 最後結果回傳到 PHP Pod，然後再顯示在瀏覽器。
 
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
--- 
3. 練習的原因
 因為在微服務架構裡，前端 Pod（例如 Web server, API Gateway）不會直接存取資料，而是去呼叫 後端 Service。
 這樣的好處是：
 (1) 不同 Service 可以獨立部署、獨立維護
 (2) Service 名稱（po1-svc）就是 cluster 內的 DNS 名稱，換 Pod 也不用擔心 IP 改變
 (3) Ingress 不需要秀出所有 Service，只要對外展示於前端就好，內部 Service 可以繼續保護在 cluster 內
---
Pod 裡的程式碼自己發 HTTP request 去連 Kubernetes Service（透過內網 DNS 解析），而不是使用者直接打 Pod。
