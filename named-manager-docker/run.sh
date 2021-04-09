#!/bin/bash
/usr/sbin/dnsmasq -u root -C /etc/dnsmasq.conf
/usr/sbin/named-checkconf -z /etc/named.conf && /usr/sbin/named -u named -c /etc/named.conf
\/usr/bin/cp /var/named/rndc.key /etc/rndc.key
/etc/init.d/namedmanager_logpush start
/usr/sbin/crond -n
# End
