<?php

/**
 * Test: Nette\DI\ContainerBuilder and resolving builtin types for generated factories. Added checks for types added in PHP 7.0.
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
		$builder->addDefinition('a')
			->setFactory('@factory::createArray');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Class or interface 'array' not found. Is return type of A\\Factory::createArray() used in service 'a' correct?");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('c')
			->setFactory('@factory::createCallable');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Class or interface 'callable' not found. Is return type of A\\Factory::createCallable() used in service 'c' correct?");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('s')
			->setFactory('@factory::createString');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Class or interface 'string' not found. Is return type of A\\Factory::createString() used in service 's' correct?");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('i')
			->setFactory('@factory::createInt');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Class or interface 'int' not found. Is return type of A\\Factory::createInt() used in service 'i' correct?");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('b')
			->setFactory('@factory::createBool');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Class or interface 'bool' not found. Is return type of A\\Factory::createBool() used in service 'b' correct?");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('f')
			->setFactory('@factory::createFloat');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Class or interface 'float' not found. Is return type of A\\Factory::createFloat() used in service 'f' correct?");

}
