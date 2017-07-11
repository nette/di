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

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('f')
			->setFactory('@factory::createObject');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Unknown type of service 'f', declare return type of factory method (for PHP 5 use annotation @return)");

	Assert::exception(function () {
		$builder = new DI\ContainerBuilder;
		$builder->addDefinition('factory')
			->setClass('A\Factory');
		$builder->addDefinition('f')
			->setFactory('@factory::createMixed');
		$container = createContainer($builder);
	}, Nette\DI\ServiceCreationException::class, "Unknown type of service 'f', declare return type of factory method (for PHP 5 use annotation @return)");

}
