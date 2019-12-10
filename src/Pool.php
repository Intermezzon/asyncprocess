<?php
namespace Intermezzon\AsyncProcess
{
	/**
	 * A Pool of many simultaneously running processes
	 */
	class Pool
	{
		/* @var int Max number of simultaneously running processes */
		private $maxProcesses = 10;

		/* @var int Microseconds to sleep while waiting for process activity */
		private $tickSleep = 100;

		/* @var array<Command> Queue of Commands */
		private $queue = [];

		/* @var array<Command> List of running Commands */
		private $runningCommands = [];

		/**
		 * Set the max number of processes running simultaneously
		 *
		 * @param int $processes
		 * @return Pool
		 */
		public function setMaxProcesses($processes)
		{
			$this->maxProcesses = $processes;
			return $this;
		}

		/**
		 * Add a new Command and associate it with this pool - but do not add it until we tell it to execute
		 *
		 * @param string $commandLine
		 * @return Command
		 */
		public function addCommand($commandLine)
		{
			$command = new Command($commandLine, $this);
			return $command;
		}

		/**
		 * Add command to queue
		 *
		 * @param Command $command
		 */
		public function addCommandToQueue($command)
		{
			$this->queue[] = $command;
		}

		/**
		 * (private) Pull Commands from queue and start executing until maxProcesses is reached
		 */
		public function _executeCommands()
		{
			while ($this->queue && (!$this->maxProcesses || count($this->runningCommands) < $this->maxProcesses)) {
				$nextCommand = array_shift($this->queue);
				$this->runningCommands[] = $nextCommand;
				$nextCommand->_execute();
			}
		}

		/**
		 * Wait for all processes in pool to be done
		 */
		public function wait()
		{
			while ($this->runningCommands || $this->queue) {
				$somethingHappened = false;
				foreach ($this->runningCommands as $command) {
					$h = $command->_monitorProcess();
					$somethingHappened = $somethingHappened || $h;
				}

				if (!$somethingHappened) {
					usleep($this->tickSleep);
				}
			}
		}

		/**
		 * (private) Cleanup will remove ended commands from runningCommands
		 */
		public function _cleanup()
		{
			for ($i = count($this->runningCommands) - 1; $i >= 0; $i--) {
				if ($this->runningCommands[$i]->hasEnded()) {
					array_splice($this->runningCommands, $i, 1);
				}
			}
		}
	}
}
