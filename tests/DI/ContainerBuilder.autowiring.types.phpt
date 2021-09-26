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


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired(Bar::class);

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::same(null, $builder->getByType(IBar::class));
	Assert::same(null, $builder->getByType(Foo::class));
	Assert::same(null, $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired('self');

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::same(null, $builder->getByType(IBar::class));
	Assert::same(null, $builder->getByType(Foo::class));
	Assert::same(null, $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired(IBar::class);

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::same('bar', $builder->getByType(IBar::class));
	Assert::same(null, $builder->getByType(Foo::class));
	Assert::same(null, $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired(Foo::class);

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::same(null, $builder->getByType(IBar::class));
	Assert::same('bar', $builder->getByType(Foo::class));
	Assert::same(null, $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired(IFoo::class);

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::same(null, $builder->getByType(IBar::class));
	Assert::same('bar', $builder->getByType(Foo::class));
	Assert::same('bar', $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired([IFoo::class, IBar::class]);

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::same('bar', $builder->getByType(IBar::class));
	Assert::same('bar', $builder->getByType(Foo::class));
	Assert::same('bar', $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired([Foo::class, Bar::class]);

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::same(null, $builder->getByType(IBar::class));
	Assert::same('bar', $builder->getByType(Foo::class));
	Assert::same(null, $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired([Foo::class, IBar::class]);

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::same('bar', $builder->getByType(IBar::class));
	Assert::same('bar', $builder->getByType(Foo::class));
	Assert::same(null, $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired([IFoo::class, Bar::class]);

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::same(null, $builder->getByType(IBar::class));
	Assert::same('bar', $builder->getByType(Foo::class));
	Assert::same('bar', $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired(Bar::class);

	$builder->addDefinition('foo')
		->setType(Foo::class)
		->setAutowired();

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::null($builder->getByType(IBar::class));
	Assert::same('foo', $builder->getByType(Foo::class));
	Assert::same('foo', $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')
		->setType(stdClass::class);

	$builder->addDefinition('two')
		->setType(stdClass::class)
		->setAutowired(stdClass::class);

	Assert::same('two', $builder->getByType(stdClass::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired([Bar::class, IFoo::class]);

	$builder->addDefinition('foo')
		->setType(Foo::class)
		->setAutowired();

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::null($builder->getByType(IBar::class));
	Assert::same('bar', $builder->getByType(Foo::class));
	Assert::same('bar', $builder->getByType(IFoo::class));
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$bar = $builder->addDefinition('bar')
		->setType(Bar::class)
		->setAutowired([Bar::class, IFoo::class]);

	$foo = $builder->addDefinition('foo')
		->setType(Foo::class)
		->setAutowired(IFoo::class);

	Assert::same('bar', $builder->getByType(Bar::class));
	Assert::null($builder->getByType(IBar::class));

	Assert::exception(function () use ($builder) {
		$builder->getByType(Foo::class);
	}, DI\ServiceCreationException::class, 'Multiple services of type Foo found: bar, foo');

	Assert::exception(function () use ($builder) {
		$builder->getByType(IFoo::class);
	}, DI\ServiceCreationException::class, 'Multiple services of type IFoo found: bar, foo');
});


test('', function () {
	$builder = new DI\ContainerBuilder;
	$bar = $builder->addDefinition('bar')
		->setType(Foo::class)
		->setAutowired([Bar::class]);

	Assert::exception(function () use ($builder) {
		$builder->getByType(Foo::class);
	}, DI\ServiceCreationException::class, "Incompatible class Bar in autowiring definition of service 'bar'.");
});
