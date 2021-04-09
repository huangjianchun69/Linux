### 1、创建named + manager 镜像：
```
docker build -f named-dockerfile.yaml --network host -t dnssrv:202104 .
```

### 2、运行docker：
```
docker run -d --restart=always --net=host -v /etc/named/dnsmasq.conf:/etc/dnsmasq.conf -v /etc/named/named.conf:/etc/named.conf -v /etc/namedmanager/config-bind.php:/etc/namedmanager/config-bind.php  dnssrv:202104
```
