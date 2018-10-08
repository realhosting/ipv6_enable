<?php
class asyncVmTasks
{
	private $phpBin = '/opt/plesk/php/7.0/bin/php';
	private $usleep = 100;
	private $tasklog;
	private $result;
	private $error;
	private $tasks;
	private $run = false;
	private $vm;
	
	// Init class
	public function __construct ($settings)
	{
		$this->tasklog = getcwd() . '/tasks/' . sha1(microtime(true));
		$this->vm = new vmConnect($settings);
	}
	
	// Close ssh session
	public function __destruct ()
	{
#		unlink($this->tasklog);
	}
	
	// Set task
	public function setTask($task)
	{
		$taskId = sha1($vmObjPacked1 . microtime());
		$this->tasks[$taskId]['task'] = $task;
		$this->tasks[$taskId]['finished'] = false;
	}
	
	private function getTaskLog()
	{
		
	}
	
	public function runTasks()
	{
		foreach ($this->tasks as $id => $task) {
			// Create processes
			$this->vm->setTask($task['task']);
			$vmObjPacked = base64_encode(serialize($this->vm));
			shell_exec("nohup {$this->phpBin} vmprocess.php $vmObjPacked $id {$this->tasklog} > /dev/null 2>&1 &");
			$this->vm->clearTasks();
			
			// spread the load
			usleep($this->usleep);
		}
		
#		while ($this->run) {
#			$tasklog = file_get_contents($this->tasklog);
			/*
			json_decode
			check off any finished tasks in 
			*/
#		}
	}
}
