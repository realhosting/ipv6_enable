<?php
$startTime = microtime(true);
$timeout = $startTime + ini_get('max_execution_time') - 20;

// Config
require_once 'config.php';

// Includes
require_once $settings['path']['base'] . '/lib/vmConnect.class.php';
require_once $settings['path']['base'] . '/lib/asyncVmTasks.class.php';
include_once $settings['path']['base'] . '/inc/getPageFromIp.php';

// VM connection object
$vm = new vmConnect($settings);

// Get domains and there IPs
$subscriptionIpquery = <<<'EOQ'
plesk db "SELECT DISTINCT d.name, GROUP_CONCAT(DISTINCT(IF(ip.public_ip_address IS NULL, ip.IP_Address, ip.public_ip_address)) SEPARATOR ', ') AS IPs FROM domains d JOIN DomainServices ds ON d.id=ds.dom_id JOIN IpAddressesCollections ipc USING(ipCollectionId) JOIN IP_Addresses ip ON ipc.ipAddressId=ip.id RIGHT JOIN Subscriptions s on s.object_id=d.id WHERE d.id IS NOT NULL GROUP BY d.name;" --xml
EOQ;

if ($vm->exec($subscriptionIpquery)) {
	foreach ($vm->getXmlResult()['row'] as $row) {
		$pleskSubscrIp[$row['field'][0]] = explode(', ', $row['field'][1]);
	}
}

$tmpfile = "{$settings['path']['task_dir']}/compare.tmp";

// Calculate difference
foreach ($pleskSubscrIp as $domain => $ip) {
	shell_exec("nohup php {$settings['path']['base']}/inc/ipdiff.cli.php $domain {$ip[0]} {$ip[1]} $tmpfile > /dev/null 2>&1 &");
	usleep(1200000);
}

do {
	if (microtime(true) > $timeout) {
		break;
	} else {
		sleep(10);
	}
	$result = explode("\n", trim(file_get_contents($tmpfile)));
	
} while (count($result) < count($pleskSubscrIp));

echo "<h3>Hostname:	{$settings['vm']['host']}</h3>";

foreach ($result as $row) {
	list($domain, $percentage) = explode(':', $row);
	$percentage = intval($percentage);
	
	if ($percentage < 90) {
		$color = 'red';
	} else if ($percentage > 99) {
		$color = 'green';
	} else {
		$color = 'orange';
	}
	echo "$domain: <b style=\"color:$color\">$percentage%</b><br>\n";
}

unlink($tmpfile);
