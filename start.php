#!/usr/bin/env php
<?php
// Config
require_once 'config.php';

// Common includes
require_once $settings['path']['base'] . '/lib/vmConnect.class.php';
require_once $settings['path']['base'] . '/lib/asyncVmTasks.class.php';

// VM connection object
$vm = new vmConnect($settings);

// Get all known system ips
$vm->exec('hostname -I');
$system['iplist'] = explode(' ', $vm->getResult());

// Get first IPv6 address from system
foreach ($system['iplist'] as $ip) {
	if(strpos($ip, ':') !== false) {
		$system['ipv6'] = $ip;
		break;
	}
}

// Communication to the user
if (!isset($system['ipv6'])) {
	$system['ipv6'] = '\033[31m This system has no IPv6 address setup. Please fix your network interface first \033[0m';
} else {
	$aaaacheck = exec('dig +short aaaa ' . $settings['vm']['host']);
	if (empty($aaaacheck)) {
		$aaaacheck = '\033[31m Warning: the hostname has no AAAA record, please add one first \033[0m';
	} else {
		$aaaacheck = 'AAAA record present for hostname (' . $aaaacheck . ')';
	}
	
	$ptrcheck = exec('dig +short -x ' . $system['ipv6']);
	if (empty($ptrcheck)) {
		$ptrcheck = '\033[31m Warning: The server IPv6 address has no PTR record, please add one first \033[0m';
	} else {
		$ptrcheck = 'PTR present for IPv6 address (' . $ptrcheck . ')';
	}
}
$stdout = <<<EOO


\033[1m Setup IPv6 for this server \033[0m

\033[1m Hostname:		\033[33m {$settings['vm']['host']} \033[0m
\033[1m New IPv6 address:	\033[33m {$system['ipv6']} \033[0m
\033[1m AAAA record:		\033[33m $aaaacheck \033[0m
\033[1m PTR record:		\033[33m $ptrcheck \033[0m

\033[1m Do you want start enabling IPv6 on this server?  Type 'yes' to continue \033[0m

EOO;

echo $stdout;

// CLI confirmation script
$handle = fopen ("php://stdin","r");
$response = fgets($handle);
if(trim($response) != 'yes'){
    echo "Aborted\n";
    exit;
}
fclose($handle);

// Start script if user confirms
echo "\nStart IPv6 enable!\n";


############ CP PLUGIN #############
require_once $settings['path']['plugin_dir'] . "/cp/{$settings['plugin']['cpplugin']}.plugin.php";

############ DNS PLUGIN ############
require_once $settings['path']['plugin_dir'] . "/dns/{$settings['plugin']['dnsplugin']}.plugin.php";


// Logging
$log[] = "\n-----------------\n\n";
$log[] = var_export($vm->getResult(true), true);
$log[] = var_export($vm->getError(true), true);
$log[] = var_export($vm->getLog(true), true);

if (is_object($dns)) {
	$log[] = var_export($dns->getResult(true), true);
	$log[] = var_export($dns->getError(true), true);
	$log[] = var_export($dns->getRequest(true), true);
	$log[] = var_export($dns->getResponse(true), true);	
}
file_put_contents($settings['path']['log_dir'] . '/' . $settings['vm']['host'] . '.log', implode("\n-----------------\n\n", $log), FILE_APPEND);


echo "\033[32m IPv6 enable is done \033[1m For more details, see this file: log/{$settings['vm']['host']}.log \033[0m\n\n";
