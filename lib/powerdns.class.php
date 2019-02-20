<?php
/**
 * Powerdns API class
 *
 * This plugin is tested on version 4.x
 *
 * @copyright  2018 Realhosting
 * @author	   R. Mik
 * @version    1.0.0 (2018-09-04)
 * @since      File available since 1.0.0 (2018-09-04)
 */
final class Powerdns
{
	/*
	 * Error value
	 */
	const HALT_EXECUTION = 'HALT_EXECUTION';

    /**
     * API connection and credential settings
	 * @var	array
     */
	protected $credentials;

    /**
     * Formatted result from json response
	 * @var	array
     */
	private $result = array();

    /**
     * Formatted errors
	 * @var	array
     */
	private $error = array();

    /**
     * API raw request data
	 * @var	array
     */
	private $request = array();

    /**
     * API raw response data
	 * @var	array
     */
	private $response = array();
	
	
 	private $curl;
	private $curl_http_code;
	private $domain_found = true;

	/**
	 * Fills $credentials with incomming connection values and inits the cURL session
	 *
	 * @param	array $credentials Control panel credentials
	 * @return	void
	 */
	public function __construct(array $credentials = array())
	{
		$this->setCredentials($credentials);
	}
	
	/**
	 * Sets the credentials
	 *
	 * @param	array $credentials Credential values
	 * @return	bool True is change is made, false if nothing changed
	 */
	public function setCredentials($credentials = array())
	{
		if (is_array($credentials)) {

			// Fill credentials with a mix of given and default values (first-run only)
			if (empty($this->credentials)) {
				$default = array(
					'hostname'			=> false,
					'admin_user'		=> false,
					'admin_pass'		=> false,
					'access_key'		=> false,
					'secure_connection' => false,
					'modified'			=> true
				);
				$this->credentials = array_merge($default, $credentials);
				return true;

			// Detect is a credentials values has changed, if so, replace
			} else {
				if (!empty($this->credentials['secure_connection'])) {
					$this->credentials['secure_connection'] = str_replace(['http://', 'https://'], '', $this->credentials['secure_connection']);
				}

				foreach ($credentials as $label => $setting) {
					if ($setting !== $this->credentials[$label]) {
						$this->credentials[$label] = $setting;
						$changed = true;
					}
				}
				if (isset($changed)) {
					$this->credentials['modified'] = true;
					return true;
				}
			}
		}
		return false;
	}

	/*
	 * Alias of $this->setCredentials()
	 */
	public function __invoke($credentials = array()) {
		$this->setCredentials($credentials);
	}

 	/**
 	 * Prepare cURL resource and cache the connection
 	 *
 	 * @return	bool If an error occurred
 	 */
 	private function prepareConnection() {
 		if ($this->credentials['modified'] OR empty($this->curl)) {

 			// Set authentication
 			// Use access key
 			if ($this->credentials['access_key'] !== false) {
 				$authHeader = ['X-API-Key: ' . $this->credentials['access_key']];

 			// Use admin user and pass
 			} else if ($this->credentials['admin_user'] !== false AND $this->credentials['admin_pass'] !== false) {
 				$authHeader = ['Authorization: Basic ' . base64_encode($this->credentials['account_user'] . ':' . $this->credentials['account_pass'])];

 			// No credentials
 			} else {
 				$this->setError('No useful credentials found');
 				return false;
 			}

 			// Set cURL options
 			$curlOptions = array(
 				CURLOPT_SSL_VERIFYPEER	=> false,
 				CURLOPT_SSL_VERIFYHOST	=> false,
 				CURLOPT_HEADER			=> false,
 				CURLOPT_RETURNTRANSFER	=> true,
 				CURLOPT_CONNECTTIMEOUT	=> 8,
 				CURLOPT_TIMEOUT			=> 600,
 				CURLOPT_HTTPHEADER		=> $authHeader
 			);

 			$this->curl = curl_init();
 			curl_setopt_array($this->curl, $curlOptions);
 		}
 	}

 	/**
 	 * Formats an API request and executes it
 	 *
 	 * @param	string $function The function that will be executed
 	 * @param	array $values Options and values for the API request
 	 * @param	array $outputFormat Formatting for the result returned
 	 * @return	bool If an error occured
 	 */
 	protected function invokeApi($requestMethod = 'GET', $values = array(), $postData = array(), $outputFormat = array())
 	{
 		if ($this->prepareConnection() === false) {
 			return false;
 		}

 		// Look for errors
 		array_walk_recursive($values, function(&$value) {
			if (strpos($value, self::HALT_EXECUTION) !== false) {
 				return false;
 			}
 		});

 		if (!isset($values['servers'])) {
 			$values = array_merge(['servers' => 'localhost'], $values);
 		}

 		$optionPath = '';
    if(isset($this->credentials['version']) && $this->credentials['version'] == 4) {
      $optionPath = '/api/v1';
    }

 		$queryOptions = array('search-log');

 		// Create option string
 		foreach ($values as $option => $value) {

 			$optionPath .= '/' . $option;

 			if ($value !== true) {
 				$value = (string) $value;

 				if (in_array($option, $queryOptions)) {
 					$optionPath .= '?q=' . $value;
 				} else {
 					$optionPath .= '/' . $value;
 				}
 			}
 		}

 		// Essemble request
 		$req = [
 			'host' => 'http' . ($this->credentials['secure_connection']?'s':'') . '://' . $this->credentials['hostname'],
 			'path' => $optionPath,
 			'data' => json_encode($postData)
 		];
 		$this->setRequest($req);

 		$curlOptions = array(
 			CURLOPT_CUSTOMREQUEST	=> $requestMethod, // GET|POST|PUT|DELETE|PATCH
 			CURLOPT_URL				=> $req['host'] . $req['path']
 		);
 		if (!empty($postData)) {
 			$curlOptions[CURLOPT_POSTFIELDS]= $req['data'];
 			$curlOptions[CURLOPT_POST]		= true;
 		} else {
 			$curlOptions[CURLOPT_POSTFIELDS]= null;
 			$curlOptions[CURLOPT_HTTPGET]	= true;
 		}
 		curl_setopt_array($this->curl, $curlOptions);

 		// Execute request
 		if (($response = curl_exec($this->curl)) === false) {
 			$error['type']	= 'curl';
 			$error['code']	= curl_errno($this->curl);
 			$error['msg']	= curl_error($this->curl);
			$this->curl_http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
 			$this->setError($error);
 			return false;
 		}
    $this->curl_http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
 		$response = iconv("UTF-8", "UTF-8//IGNORE", $response);
 		$this->setResponse($response);

 		if ($outputFormat !== false) {
 			return $this->outputHandler($response, $outputFormat);
 		} else {
 			return $response;
 		}
 	}

 	/**
 	 * Formats the API json response
 	 *
 	 * @param	string $json Json code to format
 	 * @param	string $API The used API and its version
 	 * @param	array $outputFormat Formatting for the result returned
 	 * @return	string Json formatted response
 	 */
 	protected function outputHandler($response, $arrayFormat = array())
 	{
 		$ok = true;
 		$error['type'] = 'api';
 		$result = json_decode($response, true);

		if($this->curl_http_code == 204) {
			//Result is OK for PATCH
			return true;
		} elseif ($result === null) {
			$this->setError('Response from the server is not a valid response. Expecting json');
			return false;
		}

 		if (json_last_error() !== JSON_ERROR_NONE) {
 			$result = $response;
 		}

 		// Check for errors
 		if (isset($result['error'])) {
 			$error['msg'] = $result['error'];
 			$ok = false;
 		}
 		if ($result === 'Unauthorized') {
 			$error['msg'] = 'Unauthorized';
 			$ok = false;
 		}

 		// Set error
 		if (isset($error['msg'])) {
 			$this->setError($error);
 		}

 		// Return
 		if ($ok) {
 			// Set result
 			if (isset($result['result'])) {
 				$result = $result['result'];
 			}
 			if (isset($result)) {
 				$this->setResult($result);
 			}
 			return true;
 		} else {
 			return false;
 		}
 	}

 	/**
 	 * Returns a validated value from input.
 	 * If no value, filter out settings (null values will be filtered out by Plesk::invokeApi).
 	 *
 	 * @param	mixed $input Input value to check
 	 * @param	string $default Default value in case of abscent value
 	 * @param	bool $required True if this is a required value for the API (Send a string with variable name for error formatting)
 	 * @return	string|array Validated input
 	 */
 	protected function validate(&$input = null, $default = false, $required = false)
 	{
 		// If empty value but default is present, set default as setting
 		if ($input === null AND $default !== false) {
 			$input = $default;

 		// If value is required but not present, create error
 		} else if ($input === null AND $required !== false) {
 			if (is_string($required)) {
 				$this->setError('Error: detected an empty required value for the API setting \'' . $required . '\'');
 			} else {
 				$this->setError('Error: detected an empty required value for a API setting');
 			}
 			$input = self::HALT_EXECUTION;
 		}
 		return $input;
 	}

 	/**
 	 * List servers
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function listServers(array $input = array())
 	{
 		return $this->invokeApi('GET', ['servers' => true]);
 	}

 	/**
 	 * List servers
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function getServerInfo(array $input = array())
 	{
 		return $this->invokeApi('GET');
 	}

 	/**
 	 * List all zones (extremely slow with a large amount of zones)
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function listZones(array $input = array())
 	{
 		$settings = array(
 			'zones'	=> true
 		);
 		return $this->invokeApi('GET', $settings);
 	}

 	/**
 	 * Get details from a single zone
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function getZone(array $input = array())
 	{
 		$settings = array(
 			'zones'	=> $input['domain']
 		);

 		if ($this->invokeApi('GET', $settings)) {
			$zone = $this->getResult();
			if(isset($this->credentials['version']) && $this->credentials['version'] == 4) {
				$formatted_zone = $zone;
				$formatted_zone['id'] = $this->removeDotAtEnd($formatted_zone['id']);
				$formatted_zone['name'] = $this->removeDotAtEnd($formatted_zone['name']);
				$formatted_zone['url'] = $this->removeDotAtEnd($formatted_zone['url']);

				if(isset($zone['rrsets'])) {
					unset($formatted_zone['rrsets']);
					$records = array();

					foreach($zone['rrsets'] AS $rrset) {

						if(isset($rrset['records'])) {
							$rrset['name'] = $this->removeDotAtEnd($rrset['name']);
							foreach($rrset['records'] AS $record) {
								if($rrset['type'] == 'CNAME' || $rrset['type'] == 'MX' || $rrset['type'] == 'NS') {
									$record['content'] = $this->removeDotAtEnd($record['content']);
								}

								$records[] = array(
									'name' => $rrset['name'],
									'type' => $rrset['type'],
									'ttl' => $rrset['ttl'],
									'disabled' => $record['disabled'],
									'content' => $record['content']
								);
							}
						}
					}
					$formatted_zone['records'] = $records;
				}
				//$this->setResult($formatted_zone);
				$zone = $formatted_zone;
			}

			//For v3 & v4
			if(isset($zone['records'])) {
				foreach ($zone['records'] as $key => $record) {
					$a = explode(' ', $record['content'], 2);

					if (count($a) > 1 AND is_numeric($a[0])) {
						$record['priority'] = (int) $a[0];
						$record['content'] = $a[1];
					}

					if ($record['type'] === 'TXT') {
						if (substr($record['content'], 0, 1) !== '"' AND substr($record['content'], -1, 1) !== '"') {
							$record['content'] = '"' . $record['content'] . '"';
						}
					}
					$zone['records'][$key] = $record;
				}
 			}

 			$this->setResult($zone);
 			return true;
 		}

		if (strpos($this->getError()['msg'], 'Could not find domain') !== false) {
			//Not there
			$this->setDomainFound(false);
		}
 		return false;
 	}

	private function setDomainFound($flag = false) {
		$this->domain_found = $flag;
	}

	public function getDomainFound() {
		return $this->domain_found;
	}

 	/**
 	 * Create a new zone
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function createZone(array $input = array())
 	{
 		$this->validate($input['domain'], false, 'domain');
 		if (count($input['nameservers']) < 1) {
 			$this->setError('At least 1 nameserver is required, none are found');
 			return false;
 		}

 		$settings = array(
 			'zones'	=> true
 		);

		$serial = time();
		//TODO: What if the customer wants 2017010200 ? (YYYY-MM-DD + 00 to 99)

		/*
		  SOA TTL  recommended >= 3600.
		  SOA refresh  recommended >= 14400.
		  SOA retry  recommended >= 3600.
		  SOA expire  recommended >= 604800.
		  SOA minimum  recommended between 300 and 86400.
		*/

		if(isset($this->credentials['version']) && $this->credentials['version'] == 4) {

		  foreach($input['nameservers'] AS $key => $nameserver) {
			//if doesn't end with ., add it:

			$input['nameservers'][$key] = $this->addDotAtEnd($nameserver);
		  }

		  $postData = array(
				'name'			=> $this->addDotAtEnd($input['domain']),
				'kind'			=> $this->validate($this->credentials['zone_type'], 'Master', 'zone_type'),
				'masters'		=> $this->validate($this->credentials['zone_masters'], false, 'zone_masters'),
				'nameservers'	=> $input['nameservers'],
				'rrsets' => array(
					array(
					'name'		=> $this->addDotAtEnd($input['domain']),
					'type'		=> 'SOA',
					'ttl'		=> $this->validate($input['ttl'], 3600, 'ttl'),
					'records' => array(
							array(
								'content'	=> implode(' ', [
									'primary_ns'	=> $this->addDotAtEnd($input['nameservers'][0]),
									'email_address'	=> $this->addDotAtEnd(str_replace('@', '.', $this->validate($input['soa']['email_address'], false, 'email_address'))),
									'serial'		=> $serial,
									'refresh'		=> $this->validate($input['soa']['refresh'], 86400, 'refresh'),
									'retry'			=> $this->validate($input['soa']['retry'], 7200, 'retry'),
									'expire'		=> $this->validate($input['soa']['expire'], 3600000, 'expire'),
									'default_ttl'	=> $this->validate($input['soa']['default_ttl'], 172800, 'default_ttl')
								]),
								'disabled'	=> false
							)
						)
					)
				)
			);
			
		} else { //version 3
			$postData = array(
				'name'			=> $input['domain'],
				'kind'			=> $this->validate($this->credentials['zone_type'], 'Master', 'zone_type'),
				'masters'		=> $this->validate($this->credentials['zone_masters'], false, 'zone_masters'),
				'nameservers'	=> $input['nameservers'],
				'records' => array(
					array(
						'name'		=> $input['domain'],
						'type'		=> 'SOA',
						'ttl'		=> $this->validate($input['ttl'], 3600, 'ttl'),
						'content'	=> implode(' ', [
							'primary_ns'	=> $input['nameservers'][0] . '.',
							'email_address'	=> $this->addDotAtEnd(str_replace('@', '.', $this->validate($input['soa']['email_address'], false, 'email_address'))),
							'serial'		=> $serial,
							'refresh'		=> $this->validate($input['soa']['refresh'], 86400, 'refresh'),
							'retry'			=> $this->validate($input['soa']['retry'], 7200, 'retry'),
							'expire'		=> $this->validate($input['soa']['expire'], 3600000, 'expire'),
							'default_ttl'	=> $this->validate($input['soa']['default_ttl'], 172800, 'default_ttl')
						]),
						'disabled'	=> false
					)
				)
			);
		}

 		return $this->invokeApi('POST', $settings, $postData);
 	}

 	/**
 	 * Delete a zone
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function deleteZone(array $input = array())
 	{
 		$settings = array(
 			'zones'	=> $input['domain']
 		);
		$result = $this->invokeApi('DELETE', $settings);

		if($result === false) {
			if (strpos($this->getError(), 'Could not find domain') !== false) {
				//Already gone
				$this->setResult("");
				return true;
			}
		}
 		return $result;
 	}

 	/**
 	 * Add a new record to the zone
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function addRecord(array $input = array())
 	{
 		$this->validate($input['domain'], false, 'domain');

 		// Get existing records and sort them
		$records = array();
		if($this->getZone($input) !== true) {
			$this->setError('Zone not found. Create the zone first?');
			return false;
		}
		$getresult = $this->getResult();

 		foreach ($getresult['records'] as $record) {

 			if ($record['type'] === 'SOA') {
 				continue;
 			}

 			if ($record['type'] === 'TXT') {
 				if (substr($record['content'], 0, 1) !== '"' AND substr($record['content'], -1, 1) !== '"') {
 					$record['content'] = '"' . $record['content'] . '"';
 				}
 			}

 			$sortedRecordsExisting[$record['name'] . '|' . $record['type']][] = $record;
 		}

 		if (isset($input['records']) AND is_array($input['records'])) {

 			// Sort new records by 'nametype' and look for missing values
 			foreach ($input['records'] as $record) {
 				$ok = true;

 				if (empty($record['name'])) {
 					$this->setError('Missing record value name, record will not be used');
 					$ok = false;
 				}
 				if (empty($record['type'])) {
 					$this->setError('Missing record value type, record will not be used');
 					$ok = false;
 				}
 				if (empty($record['content'])) {
 					$this->setError('Missing record value content, record will not be used');
 					$ok = false;
 				} else {
 					if (isset($record['priority'])) {
 						$record['content'] = $record['priority'] . ' ' . $record['content'];
 					}
 				}

 				if (empty($record['ttl'])) {
 					$record['ttl'] = 3600;
 					$this->setError('Missing record value ttl, using default value 3600');
 				}
 				if (empty($record['disabled'])) {
 					$record['disabled'] = false;
 				}

 				if ($ok) {
 					$sortedRecordsNew[$record['name'] . '|' . $record['type']][] = $record;
 				}
 			}

 			// If no records present, give error
 			if (isset($sortedRecordsNew)) {

 				// Look for existing records that need to be re-added as well
 				foreach ($sortedRecordsNew as $groupName => $recordGroup) {

 					// Check if record groups match
 					if (array_key_exists($groupName, $sortedRecordsExisting)) {

 						foreach ($sortedRecordsExisting[$groupName] as $existingRecord) {
 							$keepThisRecord = true;

 							foreach ($recordGroup as $record) {
 								if ($existingRecord['content'] === $record['content']) {
 									$keepThisRecord = false;
 								}
 							}
 							if ($keepThisRecord) {
 								$sortedRecordsNew[$groupName][] = $existingRecord;
 							}
 						}
 					}
 				}
 			} else {
 				$this->setError('No valid records found');
 				return false;
 			}
 		} else {
 			return false;
 		}

 		$settings = array(
 			'zones'	=> $input['domain']
 		);

 		// Add new records to the zone
 		foreach ($sortedRecordsNew as $groupName => $records) {
 			list($recordName, $recordType) = explode('|', $groupName);

			//Version 4
			if(isset($this->credentials['version']) && $this->credentials['version'] == 4) {

				$ttl = 3600;
				if(isset($records[0]['ttl'])) {
					$ttl = intval($records[0]['ttl']); //We grab the first TTL in the records
				}
				foreach($records AS $key => $record) {
				//Needs a . at the end
				$record['name'] = $this->addDotAtEnd($record['name']);

				//Each hostname (so no IP address or other content) needs a dot at the end.
				if($record['type'] == 'CNAME' || $record['type'] == 'MX' || $record['type'] == 'NS') {
					if(filter_var($record['content'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
						$record['content'] = $this->addDotAtEnd($record['content']);
					}
				}

				$records[$key] = $record;
					unset($records[$key]['ttl']);
				}

				$postData['rrsets'] = array(
					//For v4, the TTL is now inside of the RRSET, no longer in the record
					array(
						'name'			=> $recordName.'.',
						'type'			=> $recordType,
						'changetype'	=> 'REPLACE',
						'ttl'      		=> $ttl,
						'records'		=> $records
					)
				);
			} else { //v3
				$postData['rrsets'] = [
					[
						'name'			=> $recordName,
						'type'			=> $recordType,
						'changetype'	=> 'REPLACE',
						'records'		=> $records
					]
				];
			}

			if (!$this->invokeApi('PATCH', $settings, $postData)) {
				return false;
			}
 		}
 		return $this->updateSerial($input);
 	}

 	/**
 	 * Delete a record from a zone
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function deleteRecord(array $input = array())
 	{
 		$this->validate($input['domain'], false, 'domain');

 		// Get existing records and sort them
		if($this->getZone($input) === false) {
			$this->setError('Zone not found. Zone is already empty?');
			return false;
		}
 		foreach ($this->getResult()['records'] as $record) {

 			if ($record['type'] === 'SOA') {
 				continue;
 			}

 			if ($record['type'] === 'TXT') {
 				if (substr($record['content'], 0, 1) !== '"' AND substr($record['content'], -1, 1) !== '"') {
 					$record['content'] = '"' . $record['content'] . '"';
 				}
 			}

 			$sortedRecordsExisting[$record['name'] . '|' . $record['type']][] = $record;
 		}

 		if (isset($input['records']) AND is_array($input['records'])) {

 			// Sort new records by 'nametype' and look for missing values
 			foreach ($input['records'] as $record) {
 				$ok = true;

 				if (empty($record['name'])) {
 					$this->setError('Missing record value name, record will not be used');
 					$ok = false;
 				}
 				if (empty($record['type'])) {
 					$this->setError('Missing record value type, record will not be used');
 					$ok = false;
 				}
 				if ($record['type'] === 'SOA') {
 					continue;
 				}
 				if (empty($record['content'])) {
 					$this->setError('Missing record value content, record will not be used');
 					$ok = false;
 				} else {
 					if (isset($record['priority'])) {
 						$record['content'] = $record['priority'] . ' ' . $record['content'];
 					}
 				}

 				if ($ok) {
 					$sortedRecordsNew[$record['name'] . '|' . $record['type']][] = $record;
 				}
 			}

 			// If no records present, give error
 			if (isset($sortedRecordsNew)) {

 				// Look for existing records that need to be re-added as well
 				foreach ($sortedRecordsNew as $groupName => $recordGroup) {

 					$existingRecords[$groupName] = array();

 					// Check if record groups match
 					if (array_key_exists($groupName, $sortedRecordsExisting)) {

 						foreach ($sortedRecordsExisting[$groupName] as $existingRecord) {
 							$keepThisRecord = true;

 							foreach ($recordGroup as $record) {
 								if ($existingRecord['content'] === $record['content']) {
 									$keepThisRecord = false;
 								}
 							}
 							if ($keepThisRecord) {
 								$existingRecords[$groupName][] = $existingRecord;
 							}
 						}
 					}
 				}
 			} else {
 				$this->setError('No valid records found');
 				return false;
 			}
 		} else {
 			return false;
 		}

 		$settings = array(
 			'zones'	=> $input['domain']
 		);

 		// Add new records to the zone
 		foreach ($existingRecords as $groupName => $records) {
 			list($recordName, $recordType) = explode('|', $groupName);

			if(isset($this->credentials['version']) && $this->credentials['version'] == 4) {

				$ttl = 3600;
				if(isset($records[0]['ttl'])) {
					$ttl = intval($records[0]['ttl']); //We grab the first TTL in the records
				}
				foreach($records AS $key => $record) {
					// Needs a . at the end
					$record['name'] = $this->addDotAtEnd($record['name']);

					// Each hostname (so no IP address or other content) needs a dot at the end.
					if($record['type'] == 'CNAME' || $record['type'] == 'MX' || $record['type'] == 'NS') {
						if(filter_var($record['content'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
							$record['content'] = $this->addDotAtEnd($record['content']);
						}
					}
					$records[$key] = $record;
					unset($records[$key]['ttl']);
				}

				$postData['rrsets'] = [
					[
						'name'			=> $this->addDotAtEnd($recordName),
						'type'			=> $recordType,
						'ttl'     		=> $ttl,
						'changetype'	=> 'REPLACE',
						'records'		=> $records
					]
				];
			} else {
				$postData['rrsets'] = [
					[
						'name'			=> $recordName,
						'type'			=> $recordType,
						'changetype'	=> 'REPLACE',
						'records'		=> $records
					]
				];
			}

 			if (!$this->invokeApi('PATCH', $settings, $postData)) {
 				return false;
 			}
 		}
 		return $this->updateSerial($input);
 	}

 	/**
 	 * Update zone serial
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function updateSerial(array $input = array())
 	{
 		$settings = array(
 			'zones'	=> $this->validate($input['domain'], false, 'domain')
 		);

 		// Get existing SOA
 		if ($this->getZone($input)) {
 			foreach ($this->getResult()['records'] as $record) {
 				if ($record['type'] === 'SOA') {
 					$soaRecord = $record;
 					break;
 				}
 			}
 			if (empty($record) OR !isset($soaRecord)) {
 				return false;
 			}
 		} else {
 			return false;
 		}

 		$soaRecordContent = explode(' ', $soaRecord['content']);
 		if (isset($input['serial']) AND is_numeric($input['serial'])) {
 			$soaRecordContent[2] = $input['serial'];
 		} else {
 			$soaRecordContent[2] = time();
 		}

 		// Data package
		if(isset($this->credentials['version']) && $this->credentials['version'] == 4) {
			$postData['rrsets'] = [
				[
					'name'			=> $this->addDotAtEnd($soaRecord['name']),
					'type'			=> 'SOA',
					'changetype'	=> 'REPLACE',
					'ttl'			=> $soaRecord['ttl'],
					'records'		=> [
						[
							'content'	=> implode(' ', $soaRecordContent),
							'disabled'	=> false
						]
					]
				]
			];
		} else { //v3

			$postData['rrsets'] = [
				[
					'name'			=> $soaRecord['name'],
					'type'			=> 'SOA',
					'changetype'	=> 'REPLACE',
					'records'		=> [
						[
							'name'		=> $soaRecord['name'],
							'type'		=> 'SOA',
							'ttl'		=> $soaRecord['ttl'],
							'content'	=> implode(' ', $soaRecordContent),
							'disabled'	=> false
						]
					]
				]
			];
		}
		$result = $this->invokeApi('PATCH', $settings, $postData);
		if($result === true) {
			$this->getZone($input);
			return true;
		} else {
			return $result;
		}
 	}

 	/**
 	 * Notify record change
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
	public function notify(array $input = array())
	{
		if(isset($input['update_soa']) && $input['update_soa'] == true) {
			$this->updateSerial($input);
		}

 		// Notify
 		$settings = array(
 			'zones'		=> $this->validate($input['domain'], false, 'domain'),
 			'notify'	=> true
 		);
 		return $this->invokeApi('PUT', $settings);
 	}

 	/**
 	 * Get logs
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function getLogs(array $input = array())
 	{
 		$settings = array(
 			'search-log' => $input['search']
 		);

 		return $this->invokeApi('GET', $settings);
 	}

 	/**
 	 * Test API connection
 	 *
 	 * @param	array $input Input data package
 	 * @return	bool True if action was successfull, false on error
 	 */
 	public function testConnection(array $input = array())
 	{
 		if ($this->invokeApi('GET')) {
 			$this->setResult(['version' => $this->getResult()['version']]);
 			return true;
 		}
 		return false;
 	}

	private function removeDotAtEnd($content = null) {
		if(is_string($content)) {
			if( substr($content, -1) == '.') {
				$content = substr($content, 0, -1);
			}
		}
		return $content;
	}

	private function addDotAtEnd($content = null) {
		if(is_string($content)) {
			if( substr($content, -1) != '.') {
				$content = $content.'.';
			}
		}
		return $content;
	}
  
  
	/**
	 * Set API formatted result
	 *
	 * @param	string $result API formatted result
	 * @return	void
	 */
	protected function setResult($result)
	{
		if (!isset($result)) {
			$result = array();
		}
		$this->result[] = $result;
	}

	/**
	 * Get formatted API results
	 *
	 * @param	bool $all If true, everything will be returned instead of only the last entry
	 * @return	array Result data
	 */
	public function getResult($all = false)
	{
		if ($all) {
			return $this->result;
		}
		return end($this->result);
	}

	/**
	 * Set error data
	 *
	 * @param	string|array $data Error data
	 * @return	void
	 */
	protected function setError($data)
	{
		if (!is_array($data)) {
			$error['msg'] = $data;
		} else {
			$error = $data;
		}
		if (!isset($error['type'])) {
			$error['type'] = 'plugin';
		}
		$this->error[] = $error;
	}

	/**
	 * Get error data
	 *
	 * @param	bool $all If true, everything will be returned instead of only the last entry
	 * @return	array Error details
	 */
	public function getError($all = false)
	{
		if ($all) {
			return $this->error;
		}
		return end($this->error);
	}

	/**
	 * Set the current API request
	 *
	 * @param	string $request Current API request
	 * @return	void
	 */
	protected function setRequest($request)
	{
		$this->request[] = $request;
	}

	/**
	 *  Get the API request(s)
	 *
	 * @param	bool $all If true, everything will be returned instead of only the last entry
	 * @return	string|array Raw API request
	 */
	public function getRequest($all = false)
	{
		if ($all) {
			return $this->request;
		}
		return end($this->request);
	}

	/**
	 * Set raw API response
	 *
	 * @param	string $response Raw API response
	 * @return	void
	 */
	protected function setResponse($response)
	{
		$this->response[] = $response;
	}

	/**
	 *  Get raw API response
	 *
	 * @param	bool $all If true, everything will be returned instead of only the last entry
	 * @return	string|array Raw API response
	 */
	public function getResponse($all = false)
	{
		if ($all) {
			return $this->response;
		}
		return end($this->response);
	}

 }
