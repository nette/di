<?php

/**
 * Test: Nette\DI\ContainerBuilder and resolving class in generated factories. Return type is located in method signature instead of @return annotation.
 */

declare(strict_types=1);

namespace A
{
	use B\Bar;

	class Factory
	{
		public function createBar(): Bar
		{
			return new Bar;
		}
	}

}

namespace B
{

	class Bar
	{
	}

}

namespace
{
	use Nette\DI;
	use Tester\Assert;


	require __DIR__ . '/../bootstrap.php';


	$builder = new DI\ContainerBuilder;

	$builder->addDefinition('one')
		->setType(A\Factory::class);

	$builder->addDefinition('two')
		->setFactory('@one::createBar');


	$container = createContainer($builder);

	Assert::type(B\Bar::class, $container->getByType(B\Bar::class));
}
