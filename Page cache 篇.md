
1、buffer可以理解为是一类特殊文件的cache，这类特殊文件就是设备文件，比如/dev/sda1，这类设备文件的内容被读到内存后就是buffer。  
2、而cached则是普通文件的内容被读到了内存。

page cache 的理解：
```
Page Cache 是在应用程序读写文件的过程中产生的，所以在读写文件之前你需要留意是否还有足够的内存来分配 Page Cache；
Page Cache 中的脏页很容易引起问题，你要重点注意这一块；
在系统可用内存不足的时候就会回收 Page Cache 来释放出来内存，我建议你可以通过 sar 或者 /proc/vmstat 来观察这个行为从而更好的判断问题是否跟回收有关


课后作业答案：
-为什么第一次读写某个文件，Page Cache是Inactive的？
第一次读取文件后，文件内容都是inactive的，只有再次读取这些内容后，
会把它放在active链表上，处于inactive链表上的pagecache在内存紧张是会首先被回收掉，有很多情况下文件内容往往只被读一次，比如日志文件，对于这类典型的one-off文件，它们占用的pagecache需要首先被回收掉；对于业务数据，往往都会读取多次，那么他们就会被放在active链表上，以此来达到保护的目的。

-如何让它变成Active的呢？
第二次读取后，这些内容就会从inactive链表里给promote到active链表里，这也是评论区里有人提到的二次机会法。
-在什么情况下Active 的又会变成 Inactive的呢？
在内存紧张时，会进行内存回收，回收会把inactive list的部分page给回收掉，为了维持inactive/active的平衡，就需要把active list的部分page给demote到inactive list上，demote的原则也是Iru。

- 系统中有哪些控制项可以影响 Inactive与 Active Page Cache 的大小或者二者的比例？
min_free_kbytes会影响整体的pagecache大小;vfs_cache_pressure会影响在回收时回收pagecache和slab的比例； 在开启了swap的情况下，swappiness也会影响pagecache的大小；zone_reclaim_mode会影响node的pagecache大小；extfrag_threshold会影响pagecache的碎片情况。

-对于匿名页而言，当产生一个匿名页后它会首先放在 Active 链表上，请问为什么会这样子？这是合理的吗？
这是不合理的，内核社区目前在做这一块的改进。具体可以参考https://lwn.net/Articles/816771/。
```

观察page cache 数据回收：
```
# sar -B 1
pgscank/s : kswapd(后台回收线程) 每秒扫描的 page 个数。
pgscand/s: （直接回收）Application 在内存申请过程中每秒直接扫描的 page 个数。
pgsteal/s: （pgscank+pgscand）扫描的 page 中每秒被回收的个数。
%vmeff: pgsteal/(pgscank+pgscand), 回收效率，越接近 100 说明系统越安全，越接近 0 说明系统内存压力越大。如果扫描为0，这个也为0，表示内存足够不需要回收cache。
```

page cache 导致load负载升高的原因：
```
1、直接内存回收引起的load飙高。
   A: 因为直接内存回收是在进程申请内存过程中同步进行回收，而这个过程可能耗时很长，导致进程被迫等待内存回收，这样就导致业务延迟了，以及系统CPU使用率升高，load飙高。
   B: 解决方案：
      1、避免过多的直接回收内存，可以及早触发后台回收：增大 vm.min_free_kbytes 值：大概的意思是保留空闲内存为多少，单位k，看业务情况，可以设置为 524288（512M）。
      2、调大vm.vfs_cache_pressure 值，该值越大inode cache和dentry cache的回收速度会越快。越小则回收越慢，为0的时候完全不回收，内存溢出(OOM!)。
2、系统中脏页积压过多引起的load 飙高。
   A: 脏页过多可能会导致回收cache过程中进行回写落盘，会导致非常大的延迟，因为回收cache必须等数据落盘完成，这个是阻塞式的。
   B: 解决方案：控制脏页的数量：
   1、vm.dirty_background_ratio = 10 #是内存可以填充脏数据的百分比。到达这些脏数据稍后会写入磁盘。默认是10%。比如32G内存，则脏数据达到3.2G就会写入磁盘。
   2、vm.dirty_ratio = 30 #内存百分比，可以用脏数据填充的绝对最大系统内存量，当系统到达此点时，必须将所有脏数据提交到磁盘，同时所有新的I/O块都会被阻塞，直到脏数据被写入磁盘。这通常是长I/O卡顿的原因，但这也是保证内存中不会存在过量脏数据的保护机制。 
   3、vm.dirty_background_bytes 和 vm.dirty_bytes #另一种指定这些参数的方法。如果设置 xxx_bytes版本，则 xxx_ratio版本将变为0，反之亦然。 
   4、vm.dirty_expire_centisecs = 3000 #指定脏数据能存活的时间。在这里它的值是30秒（单位：百分之一秒）。
   5、vm.dirty_writeback_centisecs = 500 #指定间隔多长时间 pdflush/flush/kdmflush 这些进程会唤醒一次，然后检查是否有缓存需要清理，这里是5秒（单位：百分之一秒）。
3、系统MUMA 策略配置不当引起的load飙高。
   A: vm.zone_reclaim_mode = 0 #关闭numa。
   B: 如果设置为1，则表示node0、不能去调用node1的CPU内存，这会导致即使有空闲的内存也无法使用，导致不停的回收内存。
```

手动清理cache：
```
#非必要情况下不推荐操作，会引发系统波动，性能下降。
echo 1 > /proc/sys/vm/drop_caches #清空用户数据cache（干净页）。
echo 2 > /proc/sys/vm/drop_caches #清空内核数据cache（dentry、inode），释放inode会导致释放用户数据pagecache。
echo 3 > /proc/sys/vm/drop_caches #清空用户、内核数据cache。

#查看是否有人操作过drop cache：
# grep drop /proc/vmstat
drop_pagecache 0
drop_slab 0
如果数据不为0，则有人为或程序命令操作drop cache。

#查看文件对应的inode 被回收：
# grep inodesteal /proc/vmstat
pginodesteal 0  #kswapd 之外的其他线程回收inode 而释放的pagecache个数。
kswapd_inodesteal 0 # kswapd 后台回收inode而释放的pagecache个数。
```
避免page cache被回收而导致性能问题：
```
1、从应用代码层面来优化：
   重要的数据：用mlock(2)来保护、锁定重要的数据，避免被drop cache。
   不重要的数据：可以通过madvise(2)来告诉内核立即释放pagecache。
2、从系统层面来优化：
   使用memory cgroup来保护数据：
   memory.max：这是指 memory cgroup 内的进程最多能够分配的内存，如果不设置的话，就默认不做内存大小的限制。
   memory.high：如果设置了这一项，当 memory cgroup 内进程的内存使用量超过了该值后就会立即被回收掉，所以这一项的目的是为了尽快的回收掉不活跃的 Page Cache。
   memory.low：这一项是用来保护重要数据的，当 memory cgroup 内进程的内存使用量低于了该值后，在内存紧张触发回收后就会先去回收不属于该 memory cgroup 的 Page Cache，等到其他的 Page Cache 都被回收掉后再来回收这些 Page Cache。
   memory.min：这一项同样是用来保护重要数据的，只不过与 memoy.low 有所不同的是，当 memory cgroup 内进程的内存使用量低于该值后，即使其他不在该 memory cgroup 内的 Page Cache 都被回收完了也不会去回收这些 Page Cache，可以理解为这是用来保护最高优先级的数据的。

3、查看有哪些进程在进行直接内存回收：
echo 1 > /sys/kernel/debug/tracing/events/vmscan/mm_vmscan_direct_reclaim_begin/enable
echo 1 > /sys/kernel/debug/tracing/events/vmscan/mm_vmscan_direct_reclaim_end/enable
cat /sys/kernel/debug/tracing/trace_pipe #查看程序pid。

# 查看碎片整理进程：
 echo 1 > /sys/kernel/debug/tracing/events/compaction/mm_compaction_migratepages/enable
 echo 1 > /sys/kernel/debug/tracing/events/compaction/mm_compaction_migratepages/end
 cat /sys/kernel/debug/tracing/trace_pipe

# hcache 也可以查看到是哪个文件占用cache最多，然后使用lsof 来查看是哪个进程在使用：
GitHub：https://github.com/silenceshell/hcache
wget https://silenceshell-1255345740.cos.ap-shanghai.myqcloud.com/hcache
```

#### 观察vmstat 指标可以发现page cache 异常【/proc/vmstat】：
![image](https://note.youdao.com/yws/api/group/61831231/noteresource/F329CCDCC66041B6A986282981EF6676/version/1976?method=get-resource&shareToken=C2ED6B1454904F16A6BA32B67310CE09&entryId=432907966)

#### 观察tracepoint 做更细致的分析【/sys/kernel/debug/tracing/events/】：
![image](https://note.youdao.com/yws/api/group/61831231/noteresource/A1669FCAD00A46A884DE1E9721EE350F/version/1977?method=get-resource&shareToken=C2ED6B1454904F16A6BA32B67310CE09&entryId=432907966)
