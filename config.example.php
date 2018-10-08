<?php
$settings['vm'] = [
	'user'			=> 'root',
	'pubkey_file'	=> 'keys/pub.key',
	'privkey_file'	=> 'keys/priv.key',
	'host'			=> 'host.name',
	'port'			=> 22,
	'tunnel_host'	=> 'tunnel.host.name',
	'tunnel_port'	=> 22,
	'tunnel_enabled'=> true
];

$settings['plugin'] = [
	'cpplugin' => 'plesk',
	'dnsplugin' => 'powerdns'
];
