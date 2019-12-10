<?php
namespace Intermezzon\AsyncProcess
{
	/**
	 * A command/process
	 */
	class Command
	{
		/* @var callable Callback for errors */
		private $errorCallback = null;

		/* @var callable Callback when output from process happens */
		private $outputCallback = null;

		/* @var callable Callback when process has ended */
		private $endedCallback = null;

		/* @var callable Callback to continously monitor process */
		private $tickCallback = null;

		/* @var float Interval time for tick callback */
		private $tickInterval = null;

		/* @var float Latest time we called the tick callable */
		private $lastTickTime = null;

		/* @var string memory of all errors if we do not use our own error callback */
		public $err = '';

		/* @var string All output will end up in this variable unless we use the outputCallback */
		public $out = '';

		/* @var string Commandline for starting the process */
		private $commandLine = null;

		/* @var Pool reference to pool used to create the command */
		private $pool = null;

		/* @var resource */
		private $process = null;

		/* @var array pipes created by proc_open and used for stdin/stdout/stderr */
		private $pipes = [];

		/* @var bool Has the process ended */
		private $ended = false;

		/**
		 * Constructor
		 *
		 * @param string $commandLine
		 * @param Pool $pool reference to pool that created
		 */
		public function __construct($commandLine, $pool)
		{
			// Set default callbacks that saves the output and errors
			$this->errorCallback = function ($command, $error) {
				$command->err .= $error;
			};
			$this->outputCallback = function ($command, $output) {
				$command->out .= $output;
			};
			$this->endedCallback = function ($command, $returnCode) {
			};

			$this->commandLine = $commandLine;
			$this->pool = $pool;
		}

		/**
		 * Event when errors occurs
		 *
		 * @param callable $callback
		 * @return Command
		 */
		public function error($callback)
		{
			$this->errorCallback = $callback;
			return $this;
		}

		/**
		 * Event when process produce output
		 *
		 * @param callable $callback
		 * @return Command
		 */
		public function output($callback)
		{
			$this->outputCallback = $callback;
			return $this;
		}

		/**
		 * Event tick used to monitor och feed process with input
		 *
		 * @param callable $callback
		 * @param float $tickInterval Time in seconds between each tick (default 0.1)
		 * @return Command
		 */
		public function tick($callback, $tickInterval = 0.1)
		{
			$this->tickCallback = $callback;
			$this->tickInterval = $tickInterval;
			return $this;
		}

		/**
		 * Event when process end
		 *
		 * @param callable $callback
		 * @return Command
		 */
		public function ended($callback)
		{
			$this->endedCallback = $callback;
			return $this;
		}

		/**
		 * Send input to process stdin (may block)
		 *
		 * @param string $input
		 * @return Command
		 */
		public function input($input)
		{
			fwrite($this->pipes[0], $input);
			return $this;
		}

		/**
		 * Execute the process. It's not guaranteed that this will start the process if there is a queue in the pool.
		 * The execute command is used so that you may set all callbacks before execution starts like:
		 * $pool->addCommand('somethingslow.sh')
		 *  ->output(function ($cmd, $out) { echo $output; })
		 *  ->execute();
		 *
		 * @return Command
		 */
		public function execute()
		{
			// Add to pool queue
			$this->pool->addCommandToQueue($this);
			$this->pool->_executeCommands();
			return $this;
		}

		/**
		 * Terminate the process
		 *
		 * @return Command
		 */
		public function terminate()
		{
			if (!$this->hasEnded()) {
				proc_terminate($this->process);
			}
			return $this;
		}

		/**
		 * Has the process ended
		 *
		 * @return bool
		 */
		public function hasEnded()
		{
			return $this->ended;
		}

		/**
		 * (private) Called when process is ending for cleaning up the mess.
		 */
		public function _end($returnCode)
		{
			$this->ended = true;
			call_user_func_array($this->endedCallback, [$this, $returnCode]);
			$this->pool->_cleanup();
			$this->pool->_executeCommands();
		}

		/**
		 * (private) Do the acctual execution of the process
		 */
		public function _execute()
		{
			$fileDesc = [
				0 => ['pipe', 'r'],
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			];

			$this->process = proc_open($this->commandLine, $fileDesc, $this->pipes);

			if (!is_resource($this->process)) {
				call_user_func_array($this->errorCallback, [$this, 'Unable to start process: ' . $this->commandLine]);
				$this->_end(-1);
			} else {
				// Prepare stdin and stderr streams to be non blocking
				stream_set_blocking($this->pipes[1], false);
				stream_set_blocking($this->pipes[2], false);
			}

			$this->lastTickTime = microtime(true);
		}

		/**
		 * (private) Monitor the process continously
		 *
		 * @return bool TRUE if something happend (any output, error or process ended)
		 */
		public function _monitorProcess()
		{
			$somethingHasHappened = false;
			$status = proc_get_status($this->process);
			if (isset($this->pipes[1])) {
				// Read stdin
				if ($output = stream_get_contents($this->pipes[1])) {
					$somethingHasHappened = true;
					call_user_func_array($this->outputCallback, [$this, $output]);
				}
			}
			if (isset($this->pipes[2])) {
				// Read stderr
				if ($error = stream_get_contents($this->pipes[2])) {
					$somethingHasHappened = true;
					call_user_func_array($this->errorCallback, [$this, $error]);
				}
			}

			if (!$status['running']) {
				$somethingHasHappened = true;
				foreach ($this->pipes as $pipe) {
					fclose($pipe);
				}
				proc_close($this->process);
				$this->_end($status['exitcode']);
			} else {
				if ($this->tickCallback && ($now = microtime(true)) > $this->lastTickTime + $this->tickInterval) {
					$this->lastTickTime = $now;
					call_user_func_array($this->tickCallback, [$this]);
				}
			}

			return $somethingHasHappened;
		}
	}
}
