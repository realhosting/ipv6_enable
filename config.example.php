<?php
$basePath = $_SERVER['DOCUMENT_ROOT'] . '';

$settings = [
	// Paths
	'path' => [
		'base'			=> $basePath,
		'key_dir'		=> $basePath . '/' . 'keys',
		'task_dir'		=> $basePath . '/' . 'tasks',
		'plugin_dir'	=> $basePath . '/' . 'plugins',
		'log_dir'		=> $basePath . '/' . 'log',
	],
	// VM connection settings
	'vm' => [
		'user'			=> 'root',
		'pubkey_file'	=> 'pub.key',
		'privkey_file'	=> 'priv.key',
		'host'			=> 'host.name',
		'port'			=> 22,
		'tunnel_host'	=> 'tunnel.host.name',
		'tunnel_port'	=> 22,
		'tunnel_enabled'=> true,
	],
	// Plugins settings
	'plugin' => [
		'cpplugin'		=> 'plesk',
		'dnsplugin'		=> 'powerdns',
		'powerdns'	=> [
			'access_key'=> '',
			'hostname'	=> '127.0.0.1:8887',
			'version'	=> 4,
		],
	],
	// Misc settings
	'misc' => [
		'dns_ttl'		=> '600',
	],
];
