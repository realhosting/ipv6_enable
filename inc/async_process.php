<?php
require_once '/var/www/vhosts/tools.2is.nl/httpdocs/ipv6/lib/vmexec.class.php';
$vm = unserialize(base64_decode($argv[1]));
$taskId = $argv[2];
$file = $argv[3];

if (is_object($vm)) {
	$vm->exec();
	$line = $taskId . ':' . base64_encode($vm->getResult()) . "\n";
	file_put_contents($file, $line, FILE_APPEND);
}
