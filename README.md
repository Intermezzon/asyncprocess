# AsyncProcess

Helper to start multiple processes simultaneously.

Lets start with an example:
```php
$pool = new \Intermezzon\AsyncProcess\Pool();
$pool->addCommand('php -r "echo \"Start process.\"; sleep(2); echo \"Process ended\";"')
	->execute();

// Wait for all commands to be done
$pool->wait();
```

You may start multiple processes simultaneously
```php
$pool = new \Intermezzon\AsyncProcess\Pool();
$pool->addCommand('php -r "echo \"Start process 1.\n\"; sleep(2); echo \"Process 1 ended\n\";"')
	->execute();
$pool->addCommand('php -r "echo \"Start process 2.\n\"; sleep(1); echo \"Process 2 ended\n\";"')
	->execute();

// Wait for all commands to be done
$pool->wait();
```

## Events
You may also monitor output, errors and handle ended process
```php
$pool = new \Intermezzon\AsyncProcess\Pool();
$pool->addCommand('my_program')
	->started(function ($command)) {
		echo "Process has started\n";
	})
	->output(function ($command, $output) {
		echo "Process outputed:" . $output . "\n";
		// Send stuff to process stdin
		$command->input("send this input to process stdin");
	})
	->error(function ($command, $error) {
		echo "Process send error: " . $error ."\n";
	})
	->ended(function ($command, $exitCode) {
		echo "Process ended with exit code: " . $exitCode . "\n";
		echo "Process took " . $command->totalTime . " seconds to execute.";
	})
	->execute();

// Wait for all commands to be done
$pool->wait();
```

## Settings
You may want to limit the number of simultaneously running processes so that you do not overload your system.
```php
$pool = new \Intermezzon\AsyncProcess\Pool();
$pool->setMaxProcesses(3);
```

## Kudoz
This has been inspired by 
 - https://medium.com/datadriveninvestor/break-your-heavy-script-into-multiple-processes-in-php-c2142b993947
 - https://www.mullie.eu/parallel-processing-multi-tasking-php/

