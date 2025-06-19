<?php

/**
 * Test of simple resolving
 *
 * services:
 *    - Factory::createClass()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


class Factory
{
	public function createClass(): stdClass
	{
		return [];
	}


	public function createNullableClass(): ?stdClass
	{
		return [];
	}


	public function createScalar(): array
	{
		return [];
	}


	public function createObject(): object
	{
		return (object) null;
	}


	public function createObjectNullable(): ?object
	{
		return (object) null;
	}


	public function createMixed(): mixed
	{
		return (object) null;
	}


	/**
	 * @template T
	 * @return T
	 */
	public function createGeneric()
	{
		return (object) null;
	}


	public function createUnion(): stdClass|array
	{
		return [];
	}
}


require __DIR__ . '/../bootstrap.php';


Assert::noError(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createClass']);
	$container = createContainer($builder);
});

Assert::noError(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createNullableClass']);
	$container = createContainer($builder);
});

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createScalar']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'a']
Return type of Factory::createScalar() is expected to not be built-in/complex, 'array' given.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createObject']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'a']
Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createObjectNullable']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'a']
Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createMixed']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'a']
Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createUnion']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'a']
Return type of Factory::createUnion() is expected to not be built-in/complex, 'stdClass|array' given.");
