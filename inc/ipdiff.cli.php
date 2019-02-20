<?php
$basedir = realpath(dirname(__FILE__));
require_once $basedir . '/getPageFromIp.php';

$url = 'http://' . $argv[1];
$siteCode1 = getPageFromIp($url, $argv[2])['source'];
$siteCode2 = getPageFromIp($url, $argv[3])['source'];
if (empty($siteCode1) and empty($siteCode2)) {
	$percent = 100;
} else {
	similar_text($siteCode1, $siteCode2, $percent);
}
$result = $argv[1] . ":" . round($percent, 4) . "\n";
file_put_contents($argv[4], $result, FILE_APPEND);
