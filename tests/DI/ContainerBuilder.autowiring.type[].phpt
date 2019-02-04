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
	public $foos;
	public $strings;


	/**
	 * @param Service[] $bars
	 * @param Foo[] $foos
	 * @param string[] $strings
	 */
	public function __construct(array $bars = [], array $foos = null, array $strings = ['default'])
	{
		$this->bars = $bars;
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

$container = createContainer($builder);

$foo = $container->getService('foo');
Assert::type(Foo::class, $foo);
Assert::same([
	$container->getService('s1'),
	$container->getService('s2'),
	$container->getService('s3'),
], $foo->bars);
Assert::same([], $foo->foos);
Assert::same(['default'], $foo->strings);
