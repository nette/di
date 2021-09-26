<?php

/**
 * Test: Nette\DI\ContainerBuilder autowiring in chaining
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	/** @return Bar */
	public static function create(Test $test)
	{
		return new Bar;
	}


	public static function createUnknown()
	{
	}
}

class Bar
{
	/** @return Baz */
	public function create(Test $test)
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
Assert::type(Baz::class, $container->getByType(Baz::class));


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	- Foo()::create()::create()
	- Test
');
Assert::type(Baz::class, $container->getByType(Baz::class));


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	- Foo
	- @\Foo::create()::create()
	- Test
');
Assert::type(Baz::class, $container->getByType(Baz::class));


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	baz:
		type: Baz
		factory: Foo::createUnknown()::foo()
');
Assert::true($container->hasService('baz'));
