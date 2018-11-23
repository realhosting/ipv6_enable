<?php
// Check Plesk existance
$vm->exec('cat /opt/psa/version 2>&1');
if (strpos($vm->getResult(), 'No such file or directory') === false) {
	$pleskVersion = $vm->getResult();
} else {
	echo 'No Plesk installed on this system';
	exit;
}

// Plesk rereads ip adddresses
$vm->exec('plesk bin ipmanage --reread');

// Get all known Plesk ips ($pleskIps)
if ($vm->exec('plesk bin ipmanage -l')) {
	$pleskIPs = explode("\n", $vm->getResult());
	array_shift($pleskIPs);
	$ipmanageLabel = array('state', 'type', 'ip', 'clients', 'hosting', 'publicip');
	foreach ($pleskIPs as $ip) {
		$parts = preg_split('/\s+/', $ip);
		if (count($parts) < 6) {
			$parts[] = '';
		}
		$tmp = array_combine($ipmanageLabel, $parts);
		list(, $tmp['ip']) = explode(':', $tmp['ip'], 2);
		list($tmp['ip'], $tmp['netmask']) = explode('/', $tmp['ip'], 2);
		$pleskIps[] = $tmp;
	}
}

// Equal system IPs with Plesk IPs
foreach ($pleskIps as $ip) {
	// Set all ips to shared
	if ($ip['type'] === 'E') {
		$vm->exec("plesk bin ipmanage -u {$ip['ip']} -type shared");
	}
	// Remove unused ip addresses
	if (!in_array($ip['ip'], $system['iplist'])) {
		$vm->exec("plesk bin ipmanage -r {$ip['ip']}");
	}
}

$subscriptionIpquery = <<<'EOQ'
plesk db "SELECT DISTINCT d.name, GROUP_CONCAT(DISTINCT(IF(ip.public_ip_address IS NULL, ip.IP_Address, ip.public_ip_address)) SEPARATOR ', ') AS IPs FROM domains d JOIN DomainServices ds ON d.id=ds.dom_id JOIN IpAddressesCollections ipc USING(ipCollectionId) JOIN IP_Addresses ip ON ipc.ipAddressId=ip.id RIGHT JOIN Subscriptions s on s.object_id=d.id WHERE d.id IS NOT NULL GROUP BY d.name;" --xml
EOQ;

if ($vm->exec($subscriptionIpquery)) {
	foreach ($vm->getXmlResult()['row'] as $row) {	
		$pleskSubscrIp[$row['field'][0]] = explode(', ', $row['field'][1]);
	}
}

// Set apache restart interval
if ($vm->exec('plesk bin server_pref -s | grep restart-apache | awk \'{print $2}\'')) {
	$restartApacheValue = $vm->getResult();
} else {
	$restartApacheValue = 0;
}
$vm->exec('plesk bin server_pref -u -restart-apache 600');


$taskMan = new asyncVmTasks($settings);
// Add IPv6 address
foreach ($pleskSubscrIp as $subscription => $ips) {
	if (!in_array($system['ipv6'], $ips)) {
		$ips[] = $system['ipv6'];
	}
	$taskMan->setTask("plesk bin subscription -u $subscription -ip " . implode(',', $ips));
}

if ($taskMan->runTasks()) {
#	print_r($taskMan->getResult());
}

// Reset apache restart interval
$vm->exec("plesk bin server_pref -u -restart-apache $restartApacheValue");


// Get all Plesk domains and alias domains
if ($vm->exec('plesk db "select name from domains" --xml')) {
	foreach ($vm->getXmlResult()['row'] as $row) {	
		$domains[] = $row['field'];
	}
}
if ($vm->exec('plesk db "select name from domain_aliases" --xml')) {
	foreach ($vm->getXmlResult()['row'] as $row) {	
		$domains[] = $row['field'];
	}
}
$domains = array_unique($domains, SORT_STRING);


