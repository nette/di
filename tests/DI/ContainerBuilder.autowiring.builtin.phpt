<?php

/**
 * Test: Nette\DI\ContainerBuilder and excluding builtin types with default value from autowiring.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	public function __construct(array $arr = [])
	{
	}
}

class Bar
{
	public function __construct(array $arr = NULL)
	{
	}
}

$builder = new DI\ContainerBuilder;

$builder->addDefinition('foo')
	->setClass('Foo');
$builder->addDefinition('bar')
	->setClass('Bar');

$container = createContainer($builder);

Assert::type(Foo::class, $container->getService('foo'));
Assert::type(Bar::class, $container->getService('bar'));
