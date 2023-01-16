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
	/** @return Lorem */
	public function createClassPhpDoc()
	{
		return [];
	}


	public function createClass(): Lorem
	{
		return [];
	}


	/** @return Lorem|null */
	public function createNullableClassPhpDoc()
	{
		return [];
	}


	public function createNullableClass(): ?Lorem
	{
		return [];
	}


	/** @return array */
	public function createScalarPhpDoc()
	{
		return [];
	}


	public function createScalar(): array
	{
		return [];
	}


	/** @return object */
	public function createObjectPhpDoc()
	{
		return (object) null;
	}


	public function createObject(): object
	{
		return (object) null;
	}


	public function createObjectNullable(): ?object
	{
		return (object) null;
	}


	/** @return mixed */
	public function createMixedPhpDoc()
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
}


require __DIR__ . '/../bootstrap.php';


Assert::noError(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createClassPhpDoc']), 'next']);
	$container = @createContainer($builder); // @return is deprecated
});

Assert::noError(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createClass']), 'next']);
	$container = createContainer($builder);
});

Assert::noError(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createNullableClassPhpDoc']), 'next']);
	$container = @createContainer($builder); // @return is deprecated
});

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createNullableClass']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Return type of Factory::createNullableClass() is expected to not be nullable/built-in/complex, '?Lorem' given.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createScalarPhpDoc']), 'next']);
	$container = @createContainer($builder); // @return is deprecated
}, Nette\DI\ServiceCreationException::class, "Service 'a': Return type of Factory::createScalarPhpDoc() is expected to not be nullable/built-in/complex, 'array' given.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createScalar']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Return type of Factory::createScalar() is expected to not be nullable/built-in/complex, 'array' given.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createObjectPhpDoc']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createObject']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createObjectNullable']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createMixedPhpDoc']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createMixed']), 'next']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setFactory([new Statement([Factory::class, 'createGeneric']), 'next']);
	$container = @createContainer($builder); // @return is deprecated
}, Nette\DI\ServiceCreationException::class, "Service 'a': Class 'T' not found.
Check the return type of Factory::createGeneric().");
