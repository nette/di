<?php

/**
 * Test: Nette\DI\ContainerBuilder autowiring & implement
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface ITestFactory
{
	/** @return Test */
	function create();
}

class Foo
{
}

class Test
{
	public $foo;

	function inject(Foo $foo)
	{
		$this->foo = $foo;
	}
}


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	- Foo
	-
		implement: ITestFactory
		setup:
			- inject
');


$test = $container->getByType('ITestFactory')->create();
Assert::type(Foo::class, $test->foo);
