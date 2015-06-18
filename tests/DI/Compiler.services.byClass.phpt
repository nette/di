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


Assert::type('Lorem', $container->getService('one'));
Assert::type('Ipsum', $container->getService('two'));
Assert::type('Lorem', $container->getService('three'));
Assert::same($container->getService('one'), $container->getService('three'));
Assert::type('Lorem', $container->getService('four'));
Assert::same($container->getService('one'), $container->getService('four'));
