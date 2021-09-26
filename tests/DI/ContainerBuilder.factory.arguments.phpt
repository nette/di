<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated factories with arguments.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	public $value;


	public function __construct($value)
	{
		$this->value = $value;
	}
}

interface FooFactory
{
	/** @return Foo */
	public function create();
}


$builder = new DI\ContainerBuilder;
$builder->addFactoryDefinition('fooFactory')
	->setImplement(FooFactory::class)
	->getResultDefinition()
		->setArguments(['bar']);


$container = createContainer($builder);

Assert::type(FooFactory::class, $container->getService('fooFactory'));
Assert::type(Foo::class, $foo = $container->getService('fooFactory')->create());
Assert::same('bar', $foo->value);
