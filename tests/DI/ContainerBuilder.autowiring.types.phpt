<?php

/**
 * Test: Nette\DI\ContainerBuilder and direct types
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IFoo
{
}

interface IBar
{
}

class Foo implements IFoo
{
}

class Bar extends Foo implements IBar
{
}


test(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired('Bar');

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::same(null, $builder->getByType('IBar'));
	Assert::same(null, $builder->getByType('Foo'));
	Assert::same(null, $builder->getByType('IFoo'));
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired('IBar');

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::same('bar', $builder->getByType('IBar'));
	Assert::same(null, $builder->getByType('Foo'));
	Assert::same(null, $builder->getByType('IFoo'));
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired('Foo');

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::same(null, $builder->getByType('IBar'));
	Assert::same('bar', $builder->getByType('Foo'));
	Assert::same(null, $builder->getByType('IFoo'));
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired('IFoo');

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::same(null, $builder->getByType('IBar'));
	Assert::same('bar', $builder->getByType('Foo'));
	Assert::same('bar', $builder->getByType('IFoo'));
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired(['IFoo', 'IBar']);

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::same('bar', $builder->getByType('IBar'));
	Assert::same('bar', $builder->getByType('Foo'));
	Assert::same('bar', $builder->getByType('IFoo'));
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired(['Foo', 'Bar']);

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::same(null, $builder->getByType('IBar'));
	Assert::same('bar', $builder->getByType('Foo'));
	Assert::same(null, $builder->getByType('IFoo'));
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired(['Foo', 'IBar']);

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::same('bar', $builder->getByType('IBar'));
	Assert::same('bar', $builder->getByType('Foo'));
	Assert::same(null, $builder->getByType('IFoo'));
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired(['IFoo', 'Bar']);

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::same(null, $builder->getByType('IBar'));
	Assert::same('bar', $builder->getByType('Foo'));
	Assert::same('bar', $builder->getByType('IFoo'));
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired('Bar');

	$builder->addDefinition('foo')
		->setType('Foo')
		->setAutowired();

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::null($builder->getByType('IBar'));
	Assert::same('foo', $builder->getByType('Foo'));
	Assert::same('foo', $builder->getByType('IFoo'));
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired(['Bar', 'IFoo']);

	$builder->addDefinition('foo')
		->setType('Foo')
		->setAutowired();

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::null($builder->getByType('IBar'));
	Assert::same('bar', $builder->getByType('Foo'));
	Assert::same('bar', $builder->getByType('IFoo'));
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$bar = $builder->addDefinition('bar')
		->setType('Bar')
		->setAutowired(['Bar', 'IFoo']);

	$foo = $builder->addDefinition('foo')
		->setType('Foo')
		->setAutowired('IFoo');

	Assert::same('bar', $builder->getByType('Bar'));
	Assert::null($builder->getByType('IBar'));

	Assert::exception(function () use ($builder) {
		$builder->getByType('Foo');
	}, DI\ServiceCreationException::class, 'Multiple services of type Foo found: bar, foo');

	Assert::exception(function () use ($builder) {
		$builder->getByType('IFoo');
	}, DI\ServiceCreationException::class, 'Multiple services of type IFoo found: bar, foo');
});


test(function () {
	$builder = new DI\ContainerBuilder;
	$bar = $builder->addDefinition('bar')
		->setType('Foo')
		->setAutowired(['Bar']);

	Assert::exception(function () use ($builder) {
		$builder->getByType('Foo');
	}, DI\ServiceCreationException::class, "Incompatible class Bar in autowiring definition of service 'bar'.");
});
