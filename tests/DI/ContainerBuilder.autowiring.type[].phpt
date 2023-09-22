<?php

/**
 * Test: Nette\DI\ContainerBuilder and typehint Service[].
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	public $bars;
	public $waldos;
	public $foos;
	public $strings;


	/**
	 * @param Service[] $bars
	 * @param list<Service> $waldos
	 * @param array<int,Foo> $foos
	 * @param string[] $strings
	 */
	public function __construct(array $bars = [], array $waldos = [], ?array $foos = null, array $strings = ['default'])
	{
		$this->bars = $bars;
		$this->waldos = $waldos;
		$this->foos = $foos;
		$this->strings = $strings;
	}
}

class Service
{
}

class ServiceChild extends Service
{
}


$builder = new DI\ContainerBuilder;

$builder->addDefinition('foo')
	->setType(Foo::class);
$builder->addDefinition('s1')
	->setType(Service::class);
$builder->addDefinition('s2')
	->setType(Service::class);
$builder->addDefinition('s3')
	->setType(ServiceChild::class);
$builder->addDefinition('s4')
	->setType(stdClass::class);
$builder->addDefinition('s5')
	->setType(Service::class)
	->setAutowired(false);

$container = createContainer($builder);

$foo = $container->getService('foo');
Assert::type(Foo::class, $foo);
Assert::same([
	$container->getService('s1'),
	$container->getService('s2'),
	$container->getService('s3'),
], $foo->bars);
Assert::same([
	$container->getService('s1'),
	$container->getService('s2'),
	$container->getService('s3'),
], $foo->waldos);
Assert::same([], $foo->foos);
Assert::same(['default'], $foo->strings);


// runtime

$foo2 = $container->createInstance(Foo::class);
Assert::type(Foo::class, $foo2);
Assert::same([
	$container->getService('s1'),
	$container->getService('s2'),
	$container->getService('s3'),
], $foo2->bars);
Assert::same([
	$container->getService('s1'),
	$container->getService('s2'),
	$container->getService('s3'),
], $foo2->waldos);
Assert::same([$foo], $foo2->foos); // difference
Assert::same(['default'], $foo2->strings);
