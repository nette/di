<?php

/**
 * Test: Nette\DI\ContainerBuilder.
 */

use Nette\DI;
use Tester\Assert;


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

Assert::type(Factory::class, $container->getService('one'));
