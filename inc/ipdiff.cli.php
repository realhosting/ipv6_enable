<?php
$basedir = realpath(dirname(__FILE__));
require_once $basedir . '/getPageFromIp.php';

$url = 'http://' . $argv[1];
similar_text(getPageFromIp($url, $argv[2])['source'], getPageFromIp($url, $argv[3])['source'], $percent);
$result = $argv[1] . ":" . round($percent, 4) . "\n";
file_put_contents($argv[4], $result, FILE_APPEND);
