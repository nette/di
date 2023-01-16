<?php

/**
 * Test of chained resolving
 *
 * services:
 *    - Factory::createClass()::next()
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


class Lorem
{
	public function next(): stdClass
	{
	}
}


class Factory
{
	public function createClass(): Lorem
	{
		return [];
	}


	public function createNullableClass(): ?Lorem
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
		->setFactory([new Statement([Factory::class, 'createClass']), 'next']);
	$container = createContainer($builder);
});

Assert::noError(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createNullableClass']), 'next']);
	$container = createContainer($builder);
});

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createScalar']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'a']
Return type of Factory::createScalar() is expected to not be built-in/complex, 'array' given.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createObject']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'a']
Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createObjectNullable']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'a']
Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createMixed']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'a']
Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createUnion']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "[Service 'a']
Return type of Factory::createUnion() is expected to not be built-in/complex, 'stdClass|array' given.");
