<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


class Factory
{
	/** @return stdClass */
	public function createClassPhpDoc()
	{
		return [];
	}


	public function createClass(): stdClass
	{
		return [];
	}


	/** @return stdClass|null */
	public function createNullableClassPhpDoc()
	{
		return [];
	}


	public function createNullableClass(): ?stdClass
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
		->setCreator([Factory::class, 'createClassPhpDoc']);
	$container = @createContainer($builder); // @return is deprecated
});

Assert::noError(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createClass']);
	$container = createContainer($builder);
});

Assert::noError(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createNullableClassPhpDoc']);
	$container = @createContainer($builder); // @return is deprecated
});

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createNullableClass']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Return type of Factory::createNullableClass() is expected to not be nullable/built-in/complex, '?stdClass' given.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createScalarPhpDoc']);
	$container = @createContainer($builder); // @return is deprecated
}, Nette\DI\ServiceCreationException::class, "Service 'a': Return type of Factory::createScalarPhpDoc() is expected to not be nullable/built-in/complex, 'array' given.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createScalar']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Return type of Factory::createScalar() is expected to not be nullable/built-in/complex, 'array' given.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createObjectPhpDoc']);
	$container = @createContainer($builder); // @return is deprecated
}, Nette\DI\ServiceCreationException::class, "Service 'a': Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createObject']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createObjectNullable']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Return type of Factory::createObjectNullable() is expected to not be nullable/built-in/complex, '?object' given.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createMixedPhpDoc']);
	$container = @createContainer($builder); // @return is deprecated
}, Nette\DI\ServiceCreationException::class, "Service 'a': Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createMixed']);
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'a': Unknown service type, specify it or declare return type of factory method.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')
		->setCreator([Factory::class, 'createGeneric']);
	$container = @createContainer($builder); // @return is deprecated
}, Nette\DI\ServiceCreationException::class, "Service 'a': Class 'T' not found.
Check the return type of Factory::createGeneric().");
