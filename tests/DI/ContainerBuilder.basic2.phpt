<?php

/**
 * Test: Nette\DI\ContainerBuilder.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	private function __construct()
	{}

	/** @return Factory */
	static function create()
	{
		return new self;
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setFactory('Factory::create');


$container = createContainer($builder);

Assert::type( 'Factory', $container->getService('one') );
