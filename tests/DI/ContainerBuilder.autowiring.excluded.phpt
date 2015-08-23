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
$builder->addDefinition('xx')
		->setClass('Bar');
$builder->addExcludedClasses(['Foo', 'IBar']);

$container = createContainer($builder);

Assert::type('Bar', $container->getByType('Bar'));

Assert::exception(function () use ($container) {
	$container->getByType('IBar');
}, DI\MissingServiceException::class);

Assert::exception(function () use ($container) {
	$container->getByType('Foo');
}, DI\MissingServiceException::class);

Assert::exception(function () use ($container) {
	$container->getByType('IFoo');
}, DI\MissingServiceException::class);
