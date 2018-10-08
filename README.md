# ipv6_enable
SIDN IPv6 enable app for Plesk, PowerDNS, etc..

## Requirements
### Target server
- The target Linux VM has to have an IPv6 address setup in the network configuration
- There should be root access to the VM via a public key or by hop/tunnel server (also via a public key)

### Application server
- PHP 7.0+
- PHP SSH2 module
- PHP shell_exec enabled
- PHP max_execution_time 300+ seconds
- CLI access to the target VM
- API access to the local PowerDNS setup (or other DNS applications)


## Setup
- Greate a hosting location that passes the minimum requirements as described above.
- Copy the files of this project to the created location
- Create a key pair and copy these to the directory 'keys' (keypair should be without password)
- copy config.example.php to config.php and fill out the blanks
- Execute by using start.php

## Plugins
Plugins can be created for other systems than a Plesk/PowerDNS combinations. These can be placed in the directories 'plugins/cp' and 'plugins/dns'.

## Limitations
- Plesk is very slow and thus time consuming with most of its actions. This makes that the max_execution_time of the application server is very important for the amount of actions that can be done. Give the application server as much time as you can give it.
- Plesk plugins that are end-of-life are not supported.
