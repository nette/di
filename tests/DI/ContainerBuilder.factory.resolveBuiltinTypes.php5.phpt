<?php

/**
 * Test: Nette\DI\ContainerBuilder and resolving builtin types for generated factories.
 */

namespace A
{

	class Factory
	{
		/** @return array */
		function createArray()
		{
			return [];
		}

		/** @return callable */
		function createCallable()
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
			->setClass('A\Factory');
		$builder->addDefinition('a')
			->setFactory('@factory::createArray');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Type array used in service 'a' not found or is not class or interface.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('c')
			->setFactory('@factory::createCallable');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Type callable used in service 'c' not found or is not class or interface.");

}
