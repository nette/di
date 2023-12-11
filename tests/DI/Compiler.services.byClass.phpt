<?php

/**
 * Test: Nette\DI\Compiler: services by Class.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{
	public function __construct(Ipsum $arg)
	{
	}
}

class Ipsum
{
	public $value;


	public function __construct($value)
	{
		$this->value = $value;
	}


	public static function foo()
	{
	}
}


$container = createContainer(new DI\Compiler, '
services:
	three: @\Lorem

	one:
		create: Lorem(@\Ipsum)

	two:
		create: Ipsum(1)
		setup:
			- @\Ipsum::foo()

	four: @\Lorem

	@\Ipsum:
		arguments: [2]
');


Assert::type(Lorem::class, $container->getService('one'));
Assert::type(Ipsum::class, $container->getService('two'));
Assert::same(2, $container->getService('two')->value);
Assert::type(Lorem::class, $container->getService('three'));
Assert::same($container->getService('one'), $container->getService('three'));
Assert::type(Lorem::class, $container->getService('four'));
Assert::same($container->getService('one'), $container->getService('four'));
