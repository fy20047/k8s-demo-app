To build:

```bash
docker build -t fy20047/k8s-demo-app .
```

To run:

```bash
docker run -p 8080:80 -e MESSAGE="Hello from localhost." -e USERNAME=fy20047 -e PASSWORD=passw0rd fy20047/k8s-demo-app
```

To push:

```bash
docker push fy20047/k8s-demo-app
```
