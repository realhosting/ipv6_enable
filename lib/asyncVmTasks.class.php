<?php
#required: lib/vmConnect.class.php

class asyncVmTasks
{
	private $phpBin = '/usr/bin/php'; // /opt/plesk/php/7.0/bin/php
	private $taskDelay = 800000; // 0.8 sec
	private $tasklog;
	private $result;
	private $error;
	private $tasks;
	private $vm;
	private $logFile;
	
	// Init
	public function __construct ($settings)
	{
		$this->settings = $settings['path'];
		$this->tasklog = $this->settings['task_dir'] . '/' . sha1(microtime(true));
		$this->logFile = $this->settings['log_dir'] . '/' . $settings['vm']['host'] . '.log';
		$this->vm = new vmConnect($settings);
	}
	
	// Garbage cleanup
	public function __destruct ()
	{
		file_put_contents($this->logFile, file_get_contents($this->tasklog), FILE_APPEND);
		@unlink($this->tasklog);
	}
	
	// Set task
	public function setTask($task)
	{
		$taskId = sha1(mt_rand() . microtime());
		$this->tasks['new'][$taskId]['task'] = $task;
	}
	
	public function runTasks()
	{
		foreach ($this->tasks['new'] as $id => $task) {
			// Create processes
			$this->vm->setTask($task['task']);
			$vmObjPacked = base64_encode(serialize($this->vm));
			shell_exec("nohup {$this->phpBin} {$this->settings['base']}/inc/vmtask.cli.php $vmObjPacked $id {$this->tasklog} > /dev/null 2>&1 &");
			$this->vm->clearTasks();
			
			// spread the load
			usleep($this->taskDelay);
		}
		
		while (count($this->tasks['new']) > 0) {
			if (file_exists($this->tasklog)) {
				array_map(function($v) {
					list($taskId, $result) = explode(':', $v, 2);

					if (isset($this->tasks['new'][$taskId])) {
						$this->tasks['finished'][$taskId]['result'] = base64_decode($result);
						$this->tasks['finished'][$taskId]['task'] = $this->tasks['new'][$taskId]['task'];
						unset($this->tasks['new'][$taskId]);
					}
				}, file($this->tasklog));
			}
			usleep($this->taskDelay);
		}
		return true;
	}
	
	public function getResult()
	{
		return $this->tasks['finished'];
	}
}