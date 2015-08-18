<?php

/**
 * Test: Nette\DI\ContainerBuilder and resolving builtin types for generated factories. Added checks for types added in PHP 7.0.
 * @phpVersion 7.0
 */

namespace A
{

	class Factory
	{
		/** @return string */
		function createString()
		{
			return "";
		}

		/** @return int */
		function createInt()
		{
			return 0;
		}

		function createBool(): bool
		{
			return FALSE;
		}

		function createFloat(): float
		{
			return 0.0;
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
		$builder->addDefinition('s')
			->setFactory('@factory::createString');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Type string used in service 's' not found.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('i')
			->setFactory('@factory::createInt');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Type int used in service 'i' not found.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('b')
			->setFactory('@factory::createBool');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Type bool used in service 'b' not found.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('f')
			->setFactory('@factory::createFloat');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Type float used in service 'f' not found.");

}
