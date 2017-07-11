<?php

/**
 * Test: Nette\DI\Container static & dynamic usage.
 */

declare(strict_types=1);

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyContainer extends Container
{
	protected function createServiceOne()
	{
		return null;
	}
}


$container = new MyContainer;

Assert::true($container->hasService('one'));

$container->addService('one', new stdClass);

Assert::true($container->hasService('one'));

Assert::type(stdClass::class, $container->getService('one'));
Assert::same($container->getService('one'), $container->getService('one')); // shared
