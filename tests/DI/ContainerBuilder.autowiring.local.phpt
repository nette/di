<?php

/**
 * Test: Nette\DI\ContainerBuilder and local autowiring.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	public $arg;


	public function test(M $arg)
	{
		$this->arg = $arg;
	}
}


class M
{
}


class M1 extends M
{
}


class M2 extends M
{
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('foo')
	->setType(Foo::class);

$builder->addDefinition('m1')
	->setType(M1::class)
	->addSetup('@foo::test');

$builder->addDefinition('m2')
	->setType(M2::class)
	->addSetup('@foo::test')
	->setAutowired(false);


$container = createContainer($builder);

$foo = $container->getService('foo');
Assert::type(Foo::class, $foo);
Assert::null($foo->arg);

Assert::type(M1::class, $container->getService('m1'));
Assert::same($foo->arg, $container->getService('m1'));

Assert::type(M2::class, $container->getService('m2'));
Assert::same($foo->arg, $container->getService('m2'));
