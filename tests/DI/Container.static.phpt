<?php

/**
 * Test: Nette\DI\Container static usage.
 */

declare(strict_types=1);

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyContainer extends Container
{

	protected function createServiceOne()
	{
		return new stdClass;
	}

	protected function createServiceTwo()
	{
	}

}


$container = new MyContainer;

Assert::true($container->hasService('one'));
Assert::false($container->hasService('undefined'));

Assert::type(stdClass::class, $container->getService('one'));
Assert::same($container->getService('one'), $container->getService('one')); // shared


// bad method
Assert::exception(function () use ($container) {
	$container->getService('two');
}, Nette\UnexpectedValueException::class, "Unable to create service 'two', value returned by method createServiceTwo() is not object.");
