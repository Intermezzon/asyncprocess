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

		$command = $pool->addCommand('php -r "echo \"output\";"')
			->execute();

		$this->assertIsObject(
			$command
		);

		$pool->wait();
	}

	public function testOutput(): void
	{
		$pool = new \Intermezzon\AsyncProcess\Pool();
		$command = $pool->addCommand('php -r "echo \"START-\"; echo \"END\";"')
			->execute();
		$pool->wait();

		$this->assertSame(
			$command->out,
			"START-END"
		);
	}

	public function testError(): void
	{
		$test = $this;
		$pool = new \Intermezzon\AsyncProcess\Pool();
		$command = $pool->addCommand('>&2 echo "error"')
			->execute();
		$pool->wait();

		$this->assertSame(
			trim($command->err),
			"error"
		);
	}

	public function testMultipleProcesses(): void
	{
		$pool = new \Intermezzon\AsyncProcess\Pool();

		for ($i = 0; $i < 5; $i++) {
			$command = $pool->addCommand('php -r "echo \"START-\"; sleep(1); echo \"END\";"')
				->execute();
			$this->assertIsObject(
				$command
			);
		}
		$pool->wait();

	}

	public function testExitCode(): void
	{
		$pool = new \Intermezzon\AsyncProcess\Pool();

		$returnCode = 0;
		$command = $pool->addCommand('exit 1')
			->ended(function ($command, $rc) use (&$returnCode) {
				$returnCode = $rc;
			})
			->execute();
		$pool->wait();

		$this->assertSame(
			$returnCode,
			1
		);


	}

}
}