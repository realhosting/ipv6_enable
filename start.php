<?php
// Config
require_once '/var/www/vhosts/tools.2is.nl/httpdocs/ipv6/config.php';

// Common includes
require_once '/var/www/vhosts/tools.2is.nl/httpdocs/ipv6/lib/powerdns.class.php';
require_once '/var/www/vhosts/tools.2is.nl/httpdocs/ipv6/lib/vmexec.class.php';


$vm = new vmConnect($settings['vm']);

// Get all known system ips
$vm->exec('hostname -I');
$systemIPs = explode(' ', $vm->getResult());

// Get first IPv6 address from system
foreach ($systemIPs as $ip) {
	if(strpos($ip, ':') !== false) {
		$newIPv6 = $ip;
		break;
	}
}
if (!isset($newIPv6)) {
	echo 'This system has no IPv6 address setup. Please fix your network interface first';
	exit;
}


############ CP PLUGIN #############
require_once "/var/www/vhosts/tools.2is.nl/httpdocs/ipv6/plugins/cp/{$settings['plugin']['cpplugin']}.plugin.php";


################## DNS PLUGIN ################
require_once "/var/www/vhosts/tools.2is.nl/httpdocs/ipv6/plugins/dns/{$settings['plugin']['dnsplugin']}.plugin.php";



$vm->getResult(true);
$vm->getError(true);
$vm->getLog(true);