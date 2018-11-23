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

if (!isset($system['ipv6'])) {
	$system['ipv6'] = '<i style="color:red;">This system has no IPv6 address setup. Please fix your network interface first</i>';
} else {
	$aaaacheck = exec('dig +short aaaa ' . $settings['vm']['host']);
	if (empty($aaaacheck)) {
		$aaaacheck = '<i style="color:red;">Warning: the hostname has no AAAA record, please add one first</i>';
	} else {
		$aaaacheck = 'AAAA record present for hostname (' . $aaaacheck . ')';
	}
	
	$ptrcheck = exec('dig +short -x ' . $system['ipv6']);
	if (empty($ptrcheck)) {
		$ptrcheck = '<i style="color:red;">Warning: The server IPv6 address has no PTR record, please add one first</i>';
	} else {
		$ptrcheck = 'PTR present for IPv6 address (' . $ptrcheck . ')';
	}
}
?>
<html>
	<head>
		<title>IPv6 Enable</title>
		<style>
			html {
				font-family: arial, verdana;
			}
			#startbutton {
				font-size: 16px;
				font-weight: bold;
				background-color: #fff;
				border: 2px solid #000;
				padding: 10px;
			}
		</style>
	</head>
	<body>
		<h1>Setup IPv6 for this server</h1>
		
		<p>
			<b>Hostname:</b><br>
			<?php echo $settings['vm']['host'];?>
			<br>
			<br>
			<b>New IPv6 address:</b><br>
			<?php echo $system['ipv6'];?>
			<br>
			<br>
			<b>AAAA record:</b><br>
			<?php echo $aaaacheck?>
			<br>
			<br>
			<b>PTR record:</b><br>
			<?php echo $ptrcheck?>
			<br>
			<br>
		</p>
		<?php
		if ($_GET['status'] === 'done') {
			echo '<h2 style="color:green;">IPv6 enable is done</h2>For more details, download this <a href="log/' . $settings['vm']['host'] . '.log">log file</a>.';
		} else {			
		?>
		<form method="post" action="start.php">
			<input id="startbutton" type="submit" value="Start enabling IPv6" name="enableipv6" />
		</form>
		<?php
		}
		?>
	</body>
</html>

