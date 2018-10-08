<?php
class vmConnect
{
	private $settings;
	private $result;
	private $error;
	private $log;
	private $session;
	private $tasks;
	
	// Init class
	public function __construct ($settings)
	{
		$this->settings = $settings;
	}
	
	// Close ssh session
	public function __destruct ()
	{
		if (is_resource($this->session)) {
			ssh2_disconnect($this->session);
		}
	}
	
	// Shutdown session on serialization
    public function __sleep()
    {
		if (is_resource($this->session)) {
			ssh2_disconnect($this->session);
		}
		return array('settings', 'result', 'error', 'log', 'tasks');
    }
	
	// Set task
	public function setTask($task)
	{
		$this->tasks[] = base64_encode($task);
	}
	
	// Empty tasks
	public function clearTasks()
	{
		$this->tasks = null;
	}
	
	// Execute command
	public function exec($command = false)
	{
		if (!is_resource($this->session)) {
			$this->connect();
		}

		if (!empty($command)) {
			$tasks[] = base64_encode($command);
		} elseif (!empty($this->tasks)) {
			$tasks = $this->tasks;
			$this->tasks = null;
		} else {
			return false;
		}
		
		foreach ($tasks as $task) {
			$this->setLog($task);				
			$b64command = 'base64 -d <<< ' . $task . ' | bash';
			
			if ($this->settings['tunnel_enabled']) {
				$execute = <<<EOC
ssh -t -oBatchMode=yes -oConnectTimeout=6 -oStrictHostKeyChecking=no -p{$this->settings['port']} root@{$this->settings['host']} '{$b64command}' 2>/dev/null
EOC;
			} else {
				$execute = $b64command;
			}

			// Execute command
			$stream = ssh2_exec($this->session, $execute);
			if (is_resource($stream)) {
				stream_set_blocking($stream, true);
				
				// Get cli result
				$result = stream_get_contents($stream);
				if ($result !== false) {
					$this->setResult(trim($result));
				} else {
					$this->setError('Could not get contents');
					return false;
				}			
			} else {
				$this->setError('Could not execute command');
				return false;
			}
		}
		return true;
	}
	
	// Connect to ssh
	private function connect()
	{
		if ($this->settings['tunnel_enabled']) {
			$this->session = ssh2_connect($this->settings['tunnel_host'], $this->settings['tunnel_port']);
		} else {
			$this->session = ssh2_connect($this->settings['host'], $this->settings['port']);
		}

		if ($this->session === false OR !ssh2_auth_pubkey_file($this->session, $this->settings['user'], $this->settings['pubkey_file'], $this->settings['privkey_file'])) {
			$this->setError('Connection error');
			return false;
		}

		return true;
	}
	
	// Set result
	private function setResult($result)
	{
		$this->result[] = $result;
	}
	
	// Get result
	public function getResult($all = false)
	{
		if ($all) {
			return $this->result;
		}
		return end($this->result);
	}
	
	// Get array from parsed xml result
	public function getXmlResult()
	{
		$xmlObj = simplexml_load_string(end($this->result));
		if (is_object($xmlObj) !== false) {
			return json_decode(json_encode($xmlObj), true);
		} else {
			$this->setError('Could not create XML object, received wrong data type');
			return array();
		}
	}
	
	// Get array from parsed json result
	public function getJsonResult()
	{
		return json_decode(end($this->result), true);
	}
	
	// Set error
	private function setError($error)
	{
		$this->error[] = $error;
	}
	
	// Get error
	public function getError($all = false)
	{
		if ($all) {
			return $this->error;
		}
		return end($this->error);
	}
	
	// Set command log
	private function setLog($log)
	{
		$this->log[] = $log;
	}
	
	// Get command log
	public function getLog($log)
	{
		if ($all) {
			return $this->log;
		}
		return end(base64_decode($this->log));
	}
}
