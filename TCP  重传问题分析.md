TCP 重传指标是通过解析 /proc/net/snmp 这个文件计算出来的：  
**可以使用 sar -n ETCP 1 命令显示：**

![image](https://note.youdao.com/yws/api/group/61831231/noteresource/BF5F7F79000643C9A10EC343422348D4/version/2029?method=get-resource&shareToken=C2ED6B1454904F16A6BA32B67310CE09&entryId=432907966)

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
