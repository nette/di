<?php

/**
 * Test: Nette\DI\ContainerBuilder and excluding builtin types with default value from autowiring.
 */

declare(strict_types=1);

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
	public function __construct(array $arr = null)
	{
	}
}

$builder = new DI\ContainerBuilder;

$builder->addDefinition('foo')
	->setType(Foo::class);
$builder->addDefinition('bar')
	->setType(Bar::class);

$container = createContainer($builder);

Assert::type(Foo::class, $container->getService('foo'));
Assert::type(Bar::class, $container->getService('bar'));
