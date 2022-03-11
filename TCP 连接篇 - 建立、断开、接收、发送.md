![image](https://note.youdao.com/yws/api/group/61831231/noteresource/A4F6146E5A5B4A8B862B39334BB436D1/version/2004?method=get-resource&shareToken=C2ED6B1454904F16A6BA32B67310CE09&entryId=432907966)

#### 一、TCP 连接的建立过程受哪些配置项影响
![image](https://note.youdao.com/yws/api/group/61831231/noteresource/157D4D6990D64E399700770E1AE63B83/version/1990?method=get-resource&shareToken=C2ED6B1454904F16A6BA32B67310CE09&entryId=432907966)
- client 测调用connect()，到server 测accept() 成功返回，==**TCP 3次握手**== 过程如下：
1. client 给server 发送SYN 包。SYN 包可能会丢失，或者server 无法处理，此时 client 就会触发超时重传。  
重传次数由 ==net.ipv4.tcp_syn_retries = 2== 配置项决定，默认为6（默认6所等待的时间为1+2+4+8+16+32+64=127秒才超时，时间太长）。  
==**半连接**==：server 收到SYN 包后还没有回复 SYNACK 即为半连接。
每收到一个SYN 都会创建一个半连接，并接入到半连接队列(syn queue)中。  
**半连接队列长度** 由 ==net.ipv4.tcp_max_syn_backlog = 204800== 配置项控制。  
当超过这个数值后，新的SYN 包就会被丢弃，client 就收不到synack。  
==SYN Flood攻击==：就是针对半连接而来，半连接池满了就会拒绝服务。建议开启SYN ==cookies：net.ipv4.tcp_syncookies = 1==

2. server  向client 发送SYNACK 也可能会被丢弃，或者client 无法响应，就会触发SYNACK 重传。  
重传次数由 ==net.ipv4.tcp_synack_retries = 2== 配置项控制，默认为5。

3. client 收到 server 的 SYNACK 包后，会发出 ACK 包，server收到 ACK 后，3次握手就完成了，即生成了一个 TCP 全连接，加入到全连接队列(accept queue)中，然后server 就会调用accept() 来完成 TCP 连接建立。  
==**全连接队列**==： accept queue 也有长度限制，目的就是为了防止server 不能及时处理而浪费太多系统资源。  
**全连接队列长度** 由 ==net.core.somaxconn = 65000== 配置项控制。 当全连接超过该值后，新的全连接就会被丢弃。  
server 将新全连接丢弃时，可以发送reset 来通知client，这样client 就不会再次重传了。  
默认是不发通知，建议让client 重传，该值由 ==net.ipv4.tcp_abort_on_overflow = 0== 配置项控制，0 关闭，1 开启通知。

- 注：  
1、**net.ipv4.tcp_syn_retries = 2**：   
（1）主动新建一个连接时（也就是sock处于SYN_SEND状态时），内核要重试发送多少个SYN请求包后才决定放弃；  
（2）收到了SYN连接请求后（也就是sock处于SYN_RECV状态时），内核要重试发送多少个ACK确认包才决定放弃?  tcp_synack_retries参数一样？  
2、**net.ipv4.tcp_retries1 = 3**：在连接建立过程中（未激活的sock），除了上面的情况以外，内核要重试多少次后才决定放弃连接。  
3、**net.ipv4.tcp_retries2 = 15**：在通讯过程中（已激活的sock），数据包发送失败后，内核要重试发送多少次后才决定放弃连接。

#### 二、TCP 连接的断开过程受哪些配置项影响
![image](https://note.youdao.com/yws/api/group/61831231/noteresource/2C6405C7DC33413D9D963C5FB34676D3/version/1999?method=get-resource&shareToken=C2ED6B1454904F16A6BA32B67310CE09&entryId=432907966)
- **4 次挥手流程如下**：应用程序调用close() 时会向对端发送==FIN== 包，对端接收到FIN 包，会回应==ACK== 包，同时调用close() 来发送==FIN== 包，应用程序再回应==ACK== 包。
1. 首先调用close() 的一侧是active close（==主动关闭==），而接收到对端FIN 包后再调用close()来关闭的一侧称为passive close（==被动关闭==）
2. 有3个TCP 状态需要关注：主动关闭方的 FIN_WAIT_2 和 TIME_WAIT，以及被动关闭方的 CLOSE_WAIT.
3. **FIN_WAIT_1**：是主动关闭方发送FIN 后等待对端回应ACK 包的状态，如果对端一直不发送ACK 则会一直处于这个状态，导致超时重试。  
**FIN_WAIT_1 超时重试次数**由 ==net.ipv4.tcp_orphan_retries = 1== 配置项控制，默认是 0，那么实际等同于 8 (200+400+800+1600+3200+6400+12800+25600=51000ms，5.1秒)。  
**FIN_WAIT_1 数量**由 ==net.ipv4.tcp_max_orphans = 204800== 配置项控制，超过这个数量则会自动释放。  
**FIN_WAIT_2**：是主动关闭方等待对端发送FIN 包的状态，如果对端一直不发送FIN 则会一直处于这个状态，消耗系统资源。  
**FIN_WAIT_2 超时时间**由 ==net.ipv4.tcp_fin_timeout = 10== 配置项控制，单位秒，超过这个时间就不再等待自动销毁。
**TIME_WAIT**：是主动关闭方收到FIN后会回复ACK 包，接着就处于TIME_WAIT 状态  
**TIME_WAIT 数量**由 ==net.ipv4.tcp_max_tw_buckets = 204800== 配置项控制，连接数量超过该时，新关闭的连接就不再经历 TIME_WAIT 而直接关闭。  
**TIME_WAIT 连接复用**：由 ==net.ipv4.tcp_tw_reuse = 1== 配置项控制，该参数是只用于客户端（建立连接的发起方）起作用。
4. 被动关闭方：当你用 netstat 命令发现大量 **==CLOSE_WAIT==** 状态。就需要排查你的应用程序，因为可能因为应用程序出现了 Bug，read 函数返回 0 时，没有调用 close 函数。  
当被动方发送 FIN 报文后，连接就进入 **==LAST_ACK==** 状态，在未等到 ACK 时，会在 tcp_orphan_retries 参数的控制下重发 FIN 报文。

---
![image](https://note.youdao.com/yws/api/group/61831231/noteresource/EF79AE2AFE204CFE9D935CDDD420EAAE/version/2014?method=get-resource&shareToken=C2ED6B1454904F16A6BA32B67310CE09&entryId=432907966)

#### 三、TCP 数据包的发送过程受哪些配置项影响
![image](https://note.youdao.com/yws/api/group/61831231/noteresource/F7618E8A3BA54F3DB461FDEF64EC47A9/version/2009?method=get-resource&shareToken=C2ED6B1454904F16A6BA32B67310CE09&entryId=432907966)

1. 应用程序调用write() 或者 send() 往外发包时，这些系统调用会把数据包从用户缓冲去拷贝到tcp 发送缓冲区（tcp send buffer）。  
**发送缓冲大小** 由 ==net.ipv4.tcp_wmem = 8192 65536 16777216== 配置项控制（3个数字的含义：min default max，单位Byte字节）。同时max 值由 ==net.core.wmem_max = 16777216== 配置项控制，超过该值由net.core.wmem_max为准。  
net.ipv4.tcp_wmem/rmem 都是针对==单个tcp 连接==的。如果监控到有 ==sk_stream_wait_memory== 这个函数事件，则表示缓冲区不够了。  
**所有TCP 总内存**由 ==net.ipv4.tcp_mem = 8388608 12582912 16777216== 配置项控制。该选项中这些值的单位是 Page（页数），也就是 4K。它也有 3 个值：min、pressure、max。当所有 TCP 连接消耗的内存总和达到 max 后，也会因达到限制而无法再往外发包。如果监控到有 ==sock_exceed_buf_limit== 这个函数事件，则表示tcp 总缓冲区不够了

2. TCP 层处理完数据包后，来到IP 层，容易触发问题的是 ==net.ipv4.ip_local_port_range = 1024	65000== 表示和其他服务器建立ip 连接的端口范围。

3. 为了对TCP/IP 数据进行流控，内核在ip 层实现了==qdisc==（排队规则），TC 也是基于qdisc 实现的流控工具。  
**qdisc 规则**由 ==net.core.default_qdisc = pfifo_fast== 配置项控制。pfifo_fast（先进先出），如果使用BBR 模式，可以调整为fq（公平队列）。  
**qdisc 的队列长度**由 ==txqueuelen== 参数控制，ifconfig可以查看，如果txqueuelen 太小会导致丢包。
```
ifconfig em1 
 TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0
# dropped 不为0，那就可能是txqueuelen 太小导致的。
```
调整txqueuelen 大小：
```
ifconfig em1 txqueuelen 2000
或者
ip link set em1 txqueuelen 2000
```

#### 四、TCP 数据包的接收过程受哪些配置项影响
![image](https://note.youdao.com/yws/api/group/61831231/noteresource/EF51A892DAC243C0B516661C1D487D27/version/2012?method=get-resource&shareToken=C2ED6B1454904F16A6BA32B67310CE09&entryId=432907966)

1. 数据包到达网卡后，就好触发中断（IRQ）来告诉CPU 读取这个数据包。  
**NAPI：new api 机制**，让CPU一次性的轮询(poll)多个数据盘，提示CPU 效率，降低网卡中断带来的性能开销。  
poll 数据包的个数由 ==net.core.netdev_budget = 1000== 配置项来控制。同时也有个缺陷，如果这个值太大，会导致CPU在这里poll的时间增加，其他任务调度就会延迟。
2. CPU poll 处理完之后，数据包会到达tcp 层，这里就涉及到tcp 接收缓冲区（tcp receive buffer）  
**TCP 接收缓冲区大小**由 ==net.ipv4.tcp_rmem = 8192 65536 16777216== 配置项控制。同时max 值由 ==net.core.rmem_max = 16777216== 控制，超过该值由net.core.wmem_max为准。  
TCP 接收缓冲区也是动态在min max 之间调整的，这个动态调整可以通过  ==net.ipv4.tcp_moderate_rcvbuf = 1== 配置项来控制，0关闭、1打开。

- 缓冲里除了保存着传输的数据本身，还要预留一部分空间用来保存TCP连接本身相关的信息，换句话说，并不是所有空间都会被用来保存数据。  
- ==net.ipv4.tcp_adv_win_scale== 的值可能是 1 或者 2，如果为 1 的话，则表示二分之一的缓冲被用来做额外开销，如果为 2 的话，则表示四分之一的缓冲被用来做额外开销。
