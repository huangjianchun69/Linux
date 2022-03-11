Role Name
=========

搭建gitlab的高可用集群role，具体参考https://docs.gitlab.com/omnibus/roles/README.html#roles

Requirements
------------

至少需要4台机器，3台用于postgresql servers(1主2从), 1台用于application node.


Role Variables
--------------

* 组变量
  * web - 应用节点
  * database - 至少3个节点，默认会安装redis集群、redis sentinel集群、consul集群、postgres集群。

* role的变量和task，可以参考tests下的文件。
  * tests/web文件 - web组的特殊变量。
  * tests/database文件 - database组的特殊变量。

* 共有的变量-在defaults/main.yml，根据情况修改

Dependencies
------------

A list of other roles hosted on Galaxy should go here, plus any details in regards to parameters that may need to be set for other roles, or variables that are used from other roles.

Example Playbook
----------------

Including an example of how to use your role (for instance, with variables passed in as parameters) is always nice for users too:

    - hosts: servers
      roles:
         - { role: gitlab}
         

run playbook

    ansible-playbook -i ./hosts roles/gitlab/test/test.yml -k -b 


License
-------

BSD

Author Information
------------------

An optional section for the role authors to include contact information, or a website (HTML is not allowed).
