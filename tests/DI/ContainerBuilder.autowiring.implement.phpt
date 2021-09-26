<?php

/**
 * Test: Nette\DI\ContainerBuilder autowiring & implement
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface ITestFactory
{
	public function create(): Test;
}

class Foo
{
}

class Test
{
	public $foo;


	public function inject(Foo $foo)
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


$test = $container->getByType(ITestFactory::class)->create();
Assert::type(Foo::class, $test->foo);
