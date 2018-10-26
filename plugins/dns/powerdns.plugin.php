<?php
require_once $settings['path']['base'] . '/lib/powerdns.class.php';
$plugin = new Powerdns($settings['plugin']['powerdns']);


foreach ($domains as $domain) {
	$subZones = explode('.', $domain, substr_count($domain, '.'));

	// Look for possible subzones first before using the main DNS zone
	foreach ($subZones as $v) {
		
		$zone = implode('.', $subZones);
		array_shift($subZones);
		
		// Reset $data and set zone
		$data = array();
		$data['domain'] = $zone;

		// if zone exists, get zone data
		if ($plugin->getZone($data)) {
			$records = $plugin->getResult()['records'];
			
			// Look for already existing AAAA records with an IP from this server
			$nameExcludeList = array();
			foreach($records as $record){
				if ($record['type'] === 'AAAA' and $record['content'] === $system['ipv6']) {
					$nameExcludeList[] = $record['name'];			
				}
			}
			
			// Look for A records that need an AAAA sibling
			foreach($records as $record){
				if ($record['type'] === 'A' 
					and in_array($record['content'], $system['iplist']) 
					and !in_array($record['name'], $nameExcludeList)) {
					
					$data['records'][] = [
						'name'		=> $record['name'],
						'type'		=> 'AAAA',
						'content'	=> $system['ipv6'],
						'ttl'		=> $settings['misc']['dns_ttl']
					];
				}
			}
			
			// Add new AAAA records to this zone
			if (isset($data['records'])) {
#				$plugin->addRecord($data);
#				$plugin->notify($data)
				print_r($data);	
			}
			
			break;
		// If zone does not exist, retry with a less specific zone
		} else {
			continue;
		}
	}
}
