<?php

/**
 * Test: Nette\DI\ContainerBuilder and class blacklist
 */

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


$builder = new DI\ContainerBuilder;
$builder->addDefinition('bar')
		->setType('Bar');
$builder->addExcludedClasses(['Foo', 'IBar']);


Assert::same('bar', $builder->getByType('Bar'));
Assert::null($builder->getByType('IBar'));
Assert::null($builder->getByType('Foo'));
Assert::null($builder->getByType('IFoo'));

Assert::same(['bar'], array_keys($builder->findByType('Bar')));
Assert::same(['bar'], array_keys($builder->findByType('IBar')));
Assert::same(['bar'], array_keys($builder->findByType('Foo')));
Assert::same(['bar'], array_keys($builder->findByType('IFoo')));


$container = createContainer($builder);

Assert::type(Bar::class, $container->getByType('Bar'));

Assert::exception(function () use ($container) {
	$container->getByType('IBar');
}, DI\MissingServiceException::class);

Assert::exception(function () use ($container) {
	$container->getByType('Foo');
}, DI\MissingServiceException::class);

Assert::exception(function () use ($container) {
	$container->getByType('IFoo');
}, DI\MissingServiceException::class);

Assert::same(['bar'], $container->findByType('Bar'));
Assert::same(['bar'], $container->findByType('IBar'));
Assert::same(['bar'], $container->findByType('Foo'));
Assert::same(['bar'], $container->findByType('IFoo'));
