<?php

/**
 * Test: Nette\DI\Container circular reference detection.
 */

declare(strict_types=1);

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

Assert::exception(function () use ($container) {
	$container->getService('one');
}, Nette\InvalidStateException::class, 'Circular reference detected for: one, two.');
