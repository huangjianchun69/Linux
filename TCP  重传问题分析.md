TCP 重传指标是通过解析 /proc/net/snmp 这个文件计算出来的：  
**可以使用 sar -n ETCP 1 命令显示：**
<img width="996" alt="图片" src="https://user-images.githubusercontent.com/20528768/157797401-02ed789b-1528-4098-abc6-010591dfa340.png">


```
重传查看：  
bcc-tools：/usr/share/bcc/tools/tcpretrans 

丢包查看：  
nstat -z |grep -i drop

抓包：
tcpdump -s 0 -i eth0 -w tcpdumpfile
tshark Linux 分析工具过滤重传包：
tshark -r tcpdumpfile -R tcp.analysis.retransmission
```
