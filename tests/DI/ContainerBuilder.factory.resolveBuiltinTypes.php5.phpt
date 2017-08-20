<?php

/**
 * Test: Nette\DI\ContainerBuilder and resolving builtin types for generated factories.
 */

namespace A
{

	class Factory
	{
		/** @return array */
		public function createArray()
		{
			return [];
		}


		/** @return callable */
		public function createCallable()
		{
			return function () {};
		}
	}

}

namespace
{
	use Nette\DI;
	use Tester\Assert;


	require __DIR__ . '/../bootstrap.php';

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('a')
			->setFactory('@factory::createArray');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Class or interface 'array' not found. Is return type of A\\Factory::createArray() used in service 'a' correct?");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('c')
			->setFactory('@factory::createCallable');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Class or interface 'callable' not found. Is return type of A\\Factory::createCallable() used in service 'c' correct?");

}
