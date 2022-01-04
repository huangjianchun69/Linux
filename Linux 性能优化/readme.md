## 系统架构图
![图片](https://user-images.githubusercontent.com/20528768/148028241-4db36759-9a09-4d2a-b257-c47d81673a4e.png)

## 一、CPU 上下文切换
Q：什么叫CPU上下文切换？服务器上只有一个程序在跑为什么还有上下文切换？
   - a：系统后台进程
   - b：多进程运行，时间切片

#### CPU上下文切换的3种类型:
- 进程上下文切换
- 线程上下文切换
- 中断上下文切换


#### 进程上下文切换
1. 是指从一个进程切换到另一个进程运行。
2. 进程的上下文包括了虚拟内存、栈、全局变量、数据等用户空间资源，还包括内核堆栈、CPU寄存器等内核空间的状态。
3. 这个过程都需要消耗CPU来完成，大概需要几十纳秒到几微秒的时间，上下文切换的越多，消耗的时间就越多，真正运行进程的时间就越少。

- 进程切换场景：
   - CPU时间片公平调度。
   - 进程所需资源不足（内存等），会发生挂起切换。
   - sleep 等函数，也会将自己挂起切换。
   - 有更高优先级的进程进来。
   - 发生硬件中断，会挂起进程，去执行内核中的中断服务。

#### 线程上下文切换：
   - 不同进程的线程上下文切换，跟进程上下文切换一样。
   - 相同进程的线程上下文切换，虚拟内存、全局变量等资源不变，只切换私有数据、寄存器等不共享的数据。
   - 总结：这是多线程代替多进程的一个优势。

#### 中断上下文切换：
   - 发生硬件中断时，会立刻打断进程的正常执行，转而去处理内核的中断服务。但不影响用户态的数据。
   - 中断上下文切换只包括内核态中断服务所需要的状态（CPU寄存器、内核堆栈、硬件中断参数）
   - 中断处理比进程拥有更高的优先级，所以硬中断过多也会影响业务性能。

#### CPU指标观察工具
![图片](https://user-images.githubusercontent.com/20528768/148029072-c30b9a8b-6a6e-4901-a2ec-31627206e141.png)
![图片](https://user-images.githubusercontent.com/20528768/148029090-9eacf928-0b01-497b-941c-cebbea39de8c.png)


## 二、CPU 性能优化
- 应用程序优化：排除不必要工作，只留核心逻辑：
   1. 减少循环次数 减少递归 减少动态内存分配
   2. 算法优化
   3. 异步处理
   4. 缓存
   5. 多线程代替多进程
   6. 协程，比线程更轻量级（线程的切换由操作系统负责调度，协程由用户自己进行调度，上下文切换数据更少，执行效率比多线程高很多。）


- 系统优化：利用CPU缓存本地性，加速缓存访问；控制进程的cpu使用情况，减少程序的处理速度
   1. CPU绑定，减少跨CPU
   2. CPU独占，不允许其他程序使用
   3. 优先级调整nice，优先处理
   4. 为进程设置资源限制cgroup，防止其他进程影响
   5. NUMA优化，让CPU尽可能的访问本地内存
   6. 中断负载均衡


## 内存
Q：内存是用来做什么用的？
   - a、存放CPU的运算数据
   - b、与磁盘的数据交互
   - c、程序的运行都是在内存中
   - 简单的理解，内存其实就是系统的缓存 - page cache

#### page cache
- 1、Page Cache 是怎么来的？
   - 是应用程序读写文件的过程中产生的，所以在读写文件之前你需要留意是否还有足够的内存来分配

- 2、什么是Buffer、什么是Cache？
   - Buffer 是对磁盘数据的缓存
   - Cache 是文件数据的缓存

![图片](https://user-images.githubusercontent.com/20528768/148029643-63e3b1e6-060e-4742-9cf2-2e0b7477c987.png)


#### page cache 导致 CPU 负载升高
- page cache 怎么会导致 CPU 负载升高呢？
   - 在系统可用内存不足的时候就会回收 Page Cache，释放内存给程序使用。
   - 1、直接内存回收引起的CPU 负载升高，因为直接内存回收是在进程申请内存过程中同步进行回收，
      而这个过程可能耗时很长，导致进程被迫等待内存回收，这样就导致业务延迟了，以及系统CPU使用率升高。
   - 2、系统中脏页积压过多引起的CPU 负载升高，脏页过多可能会导致回收cache过程中进行回写落盘，
      会导致非常大的延迟，因为回收cache必须等数据落盘完成，这个是阻塞式的。
   - 3、系统NUMA 策略配置不当引起的CPU负载升高，因为NUMA默认的机制是只调用本CPU节点的内存。
      node0、不能去调用node1的CPU内存，这会导致即使有空闲的内存也无法使用，导致不停的回收内存。

#### 内存指标观察工具
![图片](https://user-images.githubusercontent.com/20528768/148030024-e8e53742-536f-4fcc-a0dc-90bfb7ec7768.png)


#### 内存的优化
- 1、代码层优化
   - 重要的数据，写代码的时候锁定好，避免被drop cache 回收掉。
   - 不重要的数据，要及时释放，避免内存浪费及内存溢出导致OOM。

- 2、系统层优化
   - a、避免过多的直接内存回收，要及早触发后台回收，增大 vm.min_free_kbytes 的值。
   - b、控制脏页的数量，让脏数据写入磁盘，避免过多的占用内存：
      - vm.dirty_background_ratio = 10   #是内存可以填充脏数据的百分比，到达这个数稍后会写入磁盘。
      - vm.dirty_ratio = 30   #可以用脏数据填充的绝对最大系统内存量，必须将所有脏数据提交到磁盘。
      - vm.dirty_expire_centisecs = 3000  #指定脏数据能存活的时间，数据落盘。
      - vm.dirty_writeback_centisecs = 500 #指定间隔多长时间，清理落盘。


## TCP连接的建立 - 三次握手
![图片](https://user-images.githubusercontent.com/20528768/148030267-957002a0-db05-4076-a776-80a777897ae5.png)

#### TCP 连接的建立过程受哪些配置项影响
- client 测调用connect()，到server 测accept() 成功返回，TCP 3次握手 过程如下：
   - 1、client 给server 发送SYN 包。SYN 包可能会丢失，或者server 无法处理，此时 client 就会触发超时重传。
      - 重传次数由 net.ipv4.tcp_syn_retries = 2 配置项决定，默认为6，共127秒才超时，时间太长。
      - 半连接：server 收到SYN 包后还没有回复 SYNACK 即为半连接。 每收到一个SYN 都会创建一个半连接，并接入到半连接队列(syn queue)中。半连接队列长度 由 net.ipv4.tcp_max_syn_backlog = 204800 配置项控制。当超过这个数值后，新的SYN 包就会被丢弃，client 就收不到synack。
      - SYN Flood攻击：就是针对半连接而来，半连接池满了就会拒绝服务。建议开启SYN cookies：net.ipv4.tcp_syncookies = 1

   - 2、server 向client 发送SYNACK 也可能会被丢弃，或者client 无法响应，就会触发SYNACK 重传。
      - 重传次数由 net.ipv4.tcp_synack_retries = 2 配置项控制，默认为5。

   - 3、client 收到 server 的 SYNACK 包后，会发出 ACK 包，server收到 ACK 后，3次握手就完成了，
      - 即生成了一个 TCP 全连接，加入到全连接队列(accept queue)中，然后server 就会调用accept()来完成 TCP 连接建立。
      - 全连接队列： accept queue 也有长度限制，目的就是为了防止server 不能及时处理而浪费太多系统资源。
      - 全连接队列长度 由 net.core.somaxconn = 65000 配置项控制。当全连接超过该值后，新的全连接就会被丢弃。
   - server 将新全连接丢弃时，可以发送reset 来通知client，这样client 就不会再次重传了。
      - 默认是不发通知，建议让client 重传，该值由 net.ipv4.tcp_abort_on_overflow = 0 配置项控制，0 关闭。


## TCP 连接的断开 - 四次挥手
![图片](https://user-images.githubusercontent.com/20528768/148030928-b860559d-6b14-49f9-968c-b42cc46b2a84.png)

#### TCP 连接的断开过程受哪些配置项影响
- 四次挥手流程如下：应用程序调用close() 时会向对端发送FIN 包，对端接收到FIN 包，会回应ACK 包，同时调用close() 来发送FIN 包，应用程序再回应ACK 包。
   - 1、首先调用close() 的一侧是active close（主动关闭），而接收到对端FIN 包后再调用close()来关闭的一侧称为passive close（被动关闭）
   - 2、有3个TCP 状态需要关注：主动关闭方的 FIN_WAIT_2 和 TIME_WAIT，以及被动关闭方的 CLOSE_WAIT.
   - 3、FIN_WAIT_1：是主动关闭方发送FIN 后等待对端回应ACK 包的状态，如果对端一直不发送ACK 则会一直处于这个状态，导致超时重试。
      - FIN_WAIT_1 超时重试次数由 net.ipv4.tcp_orphan_retries = 1 配置项控制，默认是 0，那么实际等同于 8，共5.1秒。
      - FIN_WAIT_1 数量由 net.ipv4.tcp_max_orphans = 204800 配置项控制，超过这个数量则会自动释放。
      - FIN_WAIT_2：是主动关闭方等待对端发送FIN 包的状态，如果对端一直不发送FIN 则会一直处于这个状态，消耗系统资源。
      - FIN_WAIT_2 超时时间由 net.ipv4.tcp_fin_timeout = 10 配置项控制，单位秒，超过这个时间就不再等待自动销毁。 
      - TIME_WAIT：是主动关闭方收到FIN后会回复ACK 包，接着就处于TIME_WAIT 状态。
      - TIME_WAIT 数量由 net.ipv4.tcp_max_tw_buckets = 204800 配置项控制，连接数量超过该时，新关闭的连接就不再经历 TIME_WAIT 而直接关闭。
      - TIME_WAIT 连接复用：由 net.ipv4.tcp_tw_reuse = 1 配置项控制，该参数是只用于客户端（建立连接的发起方）起作用。
   - 4、被动关闭方：当你用 netstat 命令发现大量 CLOSE_WAIT 状态。就需要排查你的应用程序，因为可能因为应用程序出现了 Bug，read 函数返回 0 时，没有调用 close 函数。
      - 当被动方发送 FIN 报文后，连接就进入 LAST_ACK 状态，在未等到 ACK 时，会在 tcp_orphan_retries 参数的控制下重发 FIN 报文。

#### TCP 数据包的发送过程受哪些配置项影响
- 1、应用程序调用write() 或者 send() 往外发包时，这些系统调用会把数据包从用户缓冲去拷贝到tcp 发送缓冲区（tcp send buffer）。
   - 发送缓冲大小 由 net.ipv4.tcp_wmem = 8192 65536 16777216 配置项控制（3个数字的含义：min default max，单位Byte字节）。
      - 同时max 值由 net.core.wmem_max = 16777216 配置项控制，超过该值由net.core.wmem_max为准。
   - net.ipv4.tcp_wmem/rmem 都是针对单个tcp 连接的。如果监控到有 sk_stream_wait_memory 这个函数事件，则表示缓冲区不够了。
   - 所有TCP 总内存由 net.ipv4.tcp_mem = 8388608 12582912 16777216 配置项控制。该选项中这些值的单位是 Page（页数），也就是 4K。
      - 它也有 3 个值：min、pressure、max。当所有 TCP 连接消耗的内存总和达到 max 后，也会因达到限制而无法再往外发包。如果监控到有 sock_exceed_buf_limit 这个函数事件，则表示tcp 总缓冲区不够了

- 2、TCP 层处理完数据包后，来到IP 层，容易触发问题的是 net.ipv4.ip_local_port_range = 1024 65000 表示和其他服务器建立ip 连接的端口范围。

- 3、为了对TCP/IP 数据进行流控，内核在ip 层实现了qdisc（排队规则），TC 也是基于qdisc 实现的流控工具。
   - qdisc 规则由 net.core.default_qdisc = pfifo_fast 配置项控制。pfifo_fast（先进先出），如果使用BBR 模式，可以调整为fq（公平队列）。
   - qdisc 的队列长度由 txqueuelen 参数控制，ifconfig可以查看，如果txqueuelen 太小会导致丢包，太高会导致延迟。
   - 调整txqueuelen 大小：
      - ifconfig em1 txqueuelen 2000
      - 或者：ip link set em1 txqueuelen 2000

#### TCP 数据包的接收过程受哪些配置项影响
- 1、数据包到达网卡后，就好触发中断（IRQ）来告诉CPU 读取这个数据包。
   - NAPI：new api 机制，让CPU一次性的轮询(poll)多个数据盘，提示CPU 效率，降低网卡中断带来的性能开销。
   - poll 数据包的个数由 net.core.netdev_budget = 1000 配置项来控制。同时也有个缺陷，如果这个值太大，会导致CPU在这里poll的时间增加，其他任务调度就会延迟。
- 2、CPU poll 处理完之后，数据包会到达tcp 层，这里就涉及到tcp 接收缓冲区（tcp receive buffer）
   - TCP 接收缓冲区大小由 net.ipv4.tcp_rmem = 8192 65536 16777216 配置项控制。
      - 同时max 值由 net.core.rmem_max = 16777216 控制，超过该值由net.core.wmem_max为准。
   - TCP 接收缓冲区也是动态在min max 之间调整的，这个动态调整可以通过 net.ipv4.tcp_moderate_rcvbuf = 1 配置项来控制，0关闭、1打开。
- 3、缓冲里除了保存着传输的数据本身，还要预留一部分空间用来保存TCP连接本身相关的信息，换句话说，并不是所有空间都会被用来保存数据。
   - net.ipv4.tcp_adv_win_scale 的值可能是 1 或者 2，如果为 1 的话，则表示二分之一的缓冲被用来做额外开销，如果为 2 的话，则表示四分之一的缓冲被用来做额外开销。




