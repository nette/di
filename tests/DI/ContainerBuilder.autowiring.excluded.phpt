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
		->setClass('Bar');
$builder->addExcludedClasses(array('Foo', 'IBar'));

$builder->prepareClassList();

Assert::same('bar', $builder->getByType('Bar'));
Assert::null($builder->getByType('IBar'));
Assert::null($builder->getByType('Foo'));
Assert::null($builder->getByType('IFoo'));

Assert::same(array('bar'), array_keys($builder->findByType('Bar')));
Assert::same(array('bar'), array_keys($builder->findByType('IBar')));
Assert::same(array('bar'), array_keys($builder->findByType('Foo')));
Assert::same(array('bar'), array_keys($builder->findByType('IFoo')));


$container = createContainer($builder);

Assert::type('Bar', $container->getByType('Bar'));

Assert::exception(function () use ($container) {
	$container->getByType('IBar');
}, '\Nette\DI\MissingServiceException');

Assert::exception(function () use ($container) {
	$container->getByType('Foo');
}, '\Nette\DI\MissingServiceException');

Assert::exception(function () use ($container) {
	$container->getByType('IFoo');
}, 'Nette\DI\MissingServiceException');

Assert::same(array('bar'), $container->findByType('Bar'));
Assert::same(array('bar'), $container->findByType('IBar'));
Assert::same(array('bar'), $container->findByType('Foo'));
Assert::same(array('bar'), $container->findByType('IFoo'));
