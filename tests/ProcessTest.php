<?php
declare(strict_types=1);

namespace Intermezzon\Tests
{

use PHPUnit\Framework\TestCase;

final class ProcessTest extends TestCase
{

	public function testOneProcess(): void
	{
		$pool = new \Intermezzon\AsyncProcess\Pool();
		$this->assertIsObject(
			$pool
		);

		$command = $pool->addCommand('php -r "echo \"output\";"');

		$this->assertIsObject(
			$command
		);

		$pool->executeAndWait();
	}

	public function testOutput(): void
	{
		$pool = new \Intermezzon\AsyncProcess\Pool();
		$command = $pool->addCommand('php -r "echo \"START-\"; echo \"END\";"');
		$pool->executeAndWait();

		$this->assertSame(
			$command->out,
			"START-END"
		);
	}

	public function testError(): void
	{
		$test = $this;
		$pool = new \Intermezzon\AsyncProcess\Pool();
		$command = $pool->addCommand('>&2 echo "error"');
		$pool->executeAndWait();

		$this->assertSame(
			trim($command->err),
			"error"
		);
	}

	public function testMultipleProcesses(): void
	{
		$pool = new \Intermezzon\AsyncProcess\Pool();

		for ($i = 0; $i < 5; $i++) {
			$command = $pool->addCommand('php -r "echo \"START-\"; sleep(1); echo \"END\";"');
			$this->assertIsObject(
				$command
			);
		}
		$pool->executeAndWait();

	}

	public function testExitCode(): void
	{
		$pool = new \Intermezzon\AsyncProcess\Pool();

		$returnCode = 0;
		$command = $pool->addCommand('exit 1')
			->ended(function ($command, $rc) use (&$returnCode) {
				$returnCode = $rc;
			});
		$pool->executeAndWait();

		$this->assertSame(
			$returnCode,
			1
		);


	}

}
}