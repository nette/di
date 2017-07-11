<?php

/**
 * Test: Nette\DI\ContainerBuilder autowiring & implement
 */

use Nette\DI;
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
