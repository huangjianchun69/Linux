### 1、创建named + manager 镜像：
```
docker build -f named-dockerfile.yaml --network host -t dnssrv:202104 .
```

### 2、单机docker 运行：
```
docker run -d --restart=always --net=host -v /etc/named/dnsmasq.conf:/etc/dnsmasq.conf -v /etc/named/named.conf:/etc/named.conf -v /etc/namedmanager/config-bind.php:/etc/namedmanager/config-bind.php  dnssrv:202104
```

### 3、k8s 集群运行：
```
#1、3个配置文件，创建 3个configmap：
kubectl create configmap dnsmasq -n bind --from-file=dnsmasq.conf
kubectl create configmap named -n bind --from-file=named.conf
kubectl create configmap namedmanager -n bind --from-file=config-bind.php

#2、启动服务：
kubectl apply -f named-deployment.yaml
```
