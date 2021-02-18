<?php

/**
 * Test: Nette\DI\ContainerBuilder and variadic parameters.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	public $bars;


	public function __construct(Service ...$bars)
	{
		$this->bars = $bars;
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
	->setType('Foo');
$builder->addDefinition('s1')
	->setType('Service');
$builder->addDefinition('s2')
	->setType('Service');
$builder->addDefinition('s3')
	->setType('ServiceChild');
$builder->addDefinition('s4')
	->setType('stdClass');
$builder->addDefinition('s5')
	->setType('Service')
	->setAutowired(false);
$builder->addDefinition('fooWithExplicitBars')
	->setType('Foo')
	->setArgument('bars', [
		$builder->getDefinition('s3'),
		$builder->getDefinition('s5'),
	]);

$container = createContainer($builder);

$foo = $container->getService('foo');
Assert::type(Foo::class, $foo);
Assert::same([
	$container->getService('s1'),
	$container->getService('s2'),
	$container->getService('s3'),
], $foo->bars);

$fooWithExplicitBars = $container->getService('fooWithExplicitBars');
Assert::type(Foo::class, $fooWithExplicitBars);
Assert::same([
	$container->getService('s3'),
	$container->getService('s5'),
], $fooWithExplicitBars->bars);


// runtime

$foo2 = $container->createInstance('Foo');
Assert::type(Foo::class, $foo2);
Assert::same([
	$container->getService('s1'),
	$container->getService('s2'),
	$container->getService('s3'),
], $foo2->bars);
