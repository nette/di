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
	}, Nette\DI\ServiceCreationException::class, "Service 'a': Class or interface 'array' not found. Check the return type of A\\Factory::createArray() method.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('c')
			->setFactory('@factory::createCallable');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 'c': Class or interface 'callable' not found. Check the return type of A\\Factory::createCallable() method.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('s')
			->setFactory('@factory::createString');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 's': Class or interface 'string' not found. Check the return type of A\\Factory::createString() method.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('i')
			->setFactory('@factory::createInt');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 'i': Class or interface 'int' not found. Check the return type of A\\Factory::createInt() method.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('b')
			->setFactory('@factory::createBool');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 'b': Class or interface 'bool' not found. Check the return type of A\\Factory::createBool() method.");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setType('A\Factory');
		$builder->addDefinition('f')
			->setFactory('@factory::createFloat');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Service 'f': Class or interface 'float' not found. Check the return type of A\\Factory::createFloat() method.");

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
