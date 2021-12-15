<?php

/**
 * Test: Nette\DI\ContainerBuilder and resolving class in generated factories. Return type is located in method signature instead of return type.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Factory
{
	public function createBar(): Bar
	{
		return new Bar;
	}
}

class Bar
{
}


$builder = new DI\ContainerBuilder;

$builder->addDefinition('one')
	->setType(Factory::class);

$builder->addDefinition('two')
	->setCreator('@one::createBar');


$container = createContainer($builder);

Assert::type(Bar::class, $container->getByType(Bar::class));
