FROM php:apache

# 外部環境變數 (寫死)
ARG MESSAGE
ENV MESSAGE=$MESSAGE

# 來自 ConfigMap
ARG USERNAME
ENV USERNAME=$USERNAME

# 來自 Secret
ARG PASSWORD
ENV PASSWORD=$PASSWORD

# 把 PHP 原始碼放到 Apache 的預設目錄
COPY src /var/www/html

# 容器開放 HTTP 服務在 80 port
EXPOSE 80 
