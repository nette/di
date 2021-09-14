<?php

/**
 * Test: Nette\DI\ContainerBuilder and resolving builtin types for generated factories. Added checks for types added in PHP 7.0.
 */

declare(strict_types=1);

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


		/** @return string */
		public function createString()
		{
			return '';
		}


		/** @return int */
		public function createInt()
		{
			return 0;
		}


		public function createBool(): bool
		{
			return false;
		}


		public function createFloat(): float
		{
			return 0.0;
		}


		/** @return object */
		public function createObject()
		{
			return (object) null;
		}


		/** @return mixed */
		public function createMixed()
		{
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
	}, Nette\DI\ServiceCreationException::class, "Service 'a': Return type of A\\Factory::createArray() is not expected to be nullable/union/intersection/built-in, 'array' given.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('c')
			->setFactory('@factory::createCallable');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 'c': Return type of A\\Factory::createCallable() is not expected to be nullable/union/intersection/built-in, 'callable' given.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('s')
			->setFactory('@factory::createString');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 's': Return type of A\\Factory::createString() is not expected to be nullable/union/intersection/built-in, 'string' given.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('i')
			->setFactory('@factory::createInt');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 'i': Return type of A\\Factory::createInt() is not expected to be nullable/union/intersection/built-in, 'int' given.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('b')
			->setFactory('@factory::createBool');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 'b': Return type of A\\Factory::createBool() is not expected to be nullable/union/intersection/built-in, 'bool' given.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('f')
			->setFactory('@factory::createFloat');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 'f': Return type of A\\Factory::createFloat() is not expected to be nullable/union/intersection/built-in, 'float' given.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('f')
			->setFactory('@factory::createObject');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 'f': Unknown service type, specify it or declare return type of factory.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('f')
			->setFactory('@factory::createMixed');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 'f': Unknown service type, specify it or declare return type of factory.");

}
