<?php
function vmExec($settings, $command) {
	$b64command = 'base64 -d <<< ' . base64_encode($command) . ' | sh';
	
	if ($settings['tunnel_enabled']) {
		$session = ssh2_connect($settings['tunnel_host'], $settings['tunnel_port']);		
		$execute = <<<EOC
ssh -t -oBatchMode=yes -oConnectTimeout=6 -oStrictHostKeyChecking=no -p{$settings['port']} root@{$settings['host']} '{$b64command}' 2>/dev/null
EOC;
	} else {
		$session = ssh2_connect($settings['host'], $settings['port']);
		$execute = $b64command;
	}
	
	if (ssh2_auth_pubkey_file($session, $settings['user'], $settings['pubkey_file'], $settings['privkey_file'])) {
		$stream = ssh2_exec($session, $execute);
		stream_set_blocking($stream, true);
		return trim(stream_get_contents($stream));
	} else {
		$error[] = 'Connection error';
	}
}
