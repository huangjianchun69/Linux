<?php

// API Configuration
$config["api_url"]              = "https://dns.domains.com:19143/namedmanager";      // Application Install Location
$config["api_server_name"]      = "dnss01.jpushoa.com";
$config["api_auth_key"]         = "slavesecretkey";                                    // API authentication key
// Log file to find messages from Named.
$config["log_file"]             = "/var/log/messages";
// Lock File
$config["lock_file"]            = "/var/lock/namedmanager_lock";
// Bind Configuration Files
$config["bind"]["version"]              = "9";                                  // version of bind (currently only 9 is supported, although others may work)
$config["bind"]["reload"]               = "/usr/sbin/rndc reload";                              // command to reload bind config & zonefiles
$config["bind"]["config"]               = "/etc/named.namedmanager.conf";       // configuration file to write bind config too
$config["bind"]["zonefiledir"]          = "/var/named/";                        // directory to write zonefiles too
                                                                                // note: if using chroot bind, will often be /var/named/chroot/var/named/
$config["bind"]["verify_zone"]          = "/usr/sbin/named-checkzone";          // Used to verify each generated zonefile as OK
$config["bind"]["verify_config"]        = "/usr/sbin/named-checkconf";          // Used to verify generated NamedManager configuration

?>
