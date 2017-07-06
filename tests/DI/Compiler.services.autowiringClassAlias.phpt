<?php

/**
 * Test: Nette\DI\Compiler and autowiring class alias.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
}

class_alias('Foo', 'FooAlias');

class Bar
{
	function __construct(FooAlias $foo)
	{
	}
}



$container = createContainer(new DI\Compiler, '
services:
	foo: FooAlias
	bar: Bar
');


Assert::type(Bar::class, $container->getService('bar'));
