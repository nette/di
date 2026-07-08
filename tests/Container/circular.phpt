<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Container circular reference detection.
 */

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyContainer extends Container
{
	protected function createServiceOne()
	{
		return $this->getService('two');
	}


	protected function createServiceTwo()
	{
		return $this->getService('one');
	}
}


$container = new MyContainer;

Assert::exception(
	fn() => $container->getService('one'),
	Nette\InvalidStateException::class,
	'Circular reference detected for: one, two.',
);
