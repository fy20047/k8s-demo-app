<!--印出伺服器資訊 + 環境變數 + URL 字串提示。-->
<pre>
<?php
  // 顯示 Server 資訊
  echo "Server: ".$_SERVER["SERVER_ADDR"].":".$_SERVER["SERVER_PORT"]." (".$_SERVER["SERVER_NAME"].")\n";
  
  // 顯示三個環境變數
  echo "Message: ".$_ENV["MESSAGE"]."\n";
  echo "Username: ".$_ENV["USERNAME"]."\n";
  echo "Password: ".$_ENV["PASSWORD"]."\n";

  // 檢查 url 參數是否存在
  // 網址 http://localhost/index.php?url=http://po1-svc
  // 在 PHP 裡 $_GET["url"] 就是 http://po1-svc
  if (isset($_GET["url"])) {
    // 不會真的去連線，只是把字串印出來，如 Response from http://po1-svc...
    echo "\nResponse from ".$_GET["url"]."...\n";
  }
?>
</pre>
<!--發出 HTTP 請求，抓取另一個 Service 的回應，模擬 Pod A 呼叫 Pod B -->
<?php
  if (isset($_GET["url"])) {
    // 從當前容器對 url 指定的網址發送一個 HTTP request
    // 真的去抓取該網址的內容，並印出來
    // 如果 url=http://po1-svc，它會去 cluster 裡請求 po1-svc 的內容
    // 把另一個 Pod/Service 回傳的資料顯示在目前的網頁上
    echo file_get_contents($_GET["url"]);
  }
?>
