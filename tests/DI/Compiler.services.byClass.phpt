<?php

/**
 * Test: Nette\DI\Compiler: services by Class.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{
	function __construct(Ipsum $arg)
	{
	}
}

class Ipsum
{
	static function foo()
	{
	}
}


$container = createContainer(new DI\Compiler, '
services:
	three: @\Lorem

	one:
		class: Lorem(@\Ipsum)

	two:
		class: Ipsum
		setup:
			- @\Ipsum::foo()

	four: @\Lorem
');


Assert::type(Lorem::class, $container->getService('one'));
Assert::type(Ipsum::class, $container->getService('two'));
Assert::type(Lorem::class, $container->getService('three'));
Assert::same($container->getService('one'), $container->getService('three'));
Assert::type(Lorem::class, $container->getService('four'));
Assert::same($container->getService('one'), $container->getService('four'));
