<?php

/**
 * Test: Nette\DI\ContainerBuilder and missing values.
 */

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
	$builder->addDefinition('foo')->setClass('Foo');
	$container = createContainer($builder);
}, 'Nette\DI\ServiceCreationException', "Service 'foo': Parameter \$x in Foo::__construct() has no class type hint or default value, so its value must be specified.");


class Bar
{
	public function __construct(array $x)
	{
	}
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('foo')->setClass('Bar');
	$container = createContainer($builder);
}, 'Nette\DI\ServiceCreationException', "Service 'foo': Parameter \$x in Bar::__construct() has no class type hint or default value, so its value must be specified.");
