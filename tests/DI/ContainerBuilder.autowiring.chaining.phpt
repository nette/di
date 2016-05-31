<?php

/**
 * Test: Nette\DI\ContainerBuilder autowiring in chaining
 */

use Nette\DI;
use Nette\DI\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	/** @return Bar */
	static function create(Test $test)
	{
		return new Bar;
	}
}

class Bar
{
	/** @return Baz */
	function create(Test $test)
	{
		return new Baz;
	}
}

class Baz
{
}

class Test
{
}


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	- Foo::create()::create()
	- Test
');
Assert::type(Baz::class, $container->getByType('Baz'));


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	- Foo()::create()::create()
	- Test
');
Assert::type(Baz::class, $container->getByType('Baz'));


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	- Foo
	- @\Foo::create()::create()
	- Test
');
Assert::type(Baz::class, $container->getByType('Baz'));
