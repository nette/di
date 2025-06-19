<?php

/**
 * Test: Nette\DI\ContainerBuilder and missing values.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	public function __construct($x)
	{
	}
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('foo')->setType(Foo::class);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'foo' of type Foo]
Parameter \$x in Foo::__construct() has no class type or default value, so its value must be specified.");


class Bar
{
	public function __construct(array $x)
	{
	}
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('foo')->setType(Bar::class);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'foo' of type Bar]
Parameter \$x in Bar::__construct() has no class type or default value, so its value must be specified.");


class Bar2
{
	public function __construct(array $x = [])
	{
	}
}

Assert::noError(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('foo')->setType(Bar2::class);
	$container = createContainer($builder);
});


class Bar3
{
	public function __construct(?stdClass $x = null)
	{
	}
}

Assert::noError(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('foo')->setType(Bar3::class);
	$container = createContainer($builder);
});
