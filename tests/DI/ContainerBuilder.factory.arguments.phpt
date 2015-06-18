<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated factories with arguments.
 */

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
$builder->addDefinition('fooFactory')
		->setImplement('FooFactory')
		->setArguments(array('bar'));


$container = createContainer($builder);

Assert::type('FooFactory', $container->getService('fooFactory'));
Assert::type('Foo', $foo = $container->getService('fooFactory')->create());
Assert::same('bar', $foo->value);
