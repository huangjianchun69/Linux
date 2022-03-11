# 常用命令

## 查看日志

    sudo gitlab-ctl tail
    sudo gitlab-ctl tail consul

## pgsql集群创建

    sudo gitlab-ctl repmgr cluster show -找出master节点和显示状态。
    sudo gitlab-ctl repmgr standby setup MASTER_NODE_NAME -备节点上执行。

注：
1. 不能同时出现两个master，否则web会出现502。
2. pgsql集群，必须能互ping主机名，也就是写死到/etc/hosts里。

## 清理所有缓存

    sudo gitlab-rake cache:clear

## 重新生成ssh key

    sudo gitlab-rake gitlab:shell:setup

## 备份和还原

    sudo gitlab-rake gitlab:backup:create -备份
    sudo gitlab-rake gitlab:backup:restore BACKUP=1552091928_2019_03_09_11.8.1-ee -还原
