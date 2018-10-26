<?php
#required: lib/vmConnect.class.php

class asyncVmTasks
{
	private $phpBin = '/opt/plesk/php/7.0/bin/php';
	private $usleep = 500000; // 0.3 sec
	private $tasklog;
	private $result;
	private $error;
	private $tasks;
	private $vm;
	
	// Init
	public function __construct ($settings)
	{
		$this->settings = $settings['path'];
		
		$this->tasklog = $this->settings['task_dir'] . '/' . sha1(microtime(true));
		$this->vm = new vmConnect($settings);
	}
	
	// Garbage cleanup
	public function __destruct ()
	{
		@unlink($this->tasklog);
	}
	
	// Set task
	public function setTask($task)
	{
		$taskId = sha1($vmObjPacked1 . microtime());
		$this->tasks['new'][$taskId]['task'] = $task;
	}
	
	public function runTasks()
	{
		foreach ($this->tasks['new'] as $id => $task) {
			// Create processes
			$this->vm->setTask($task['task']);
			$vmObjPacked = base64_encode(serialize($this->vm));
			shell_exec("nohup {$this->phpBin} {$this->settings['base']}/inc/async_process.php $vmObjPacked $id {$this->tasklog} > /dev/null 2>&1 &");
			$this->vm->clearTasks();
			
			// spread the load
			usleep($this->usleep);
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
			usleep($this->usleep);
		}
		return true;
	}
	
	public function getResult()
	{
		return $this->tasks['finished'];
	}
}