<?php

/**
 * Test: Nette\DI\Container::getByType() and findByType()
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service extends Nette\Object
{
}

class Child extends Service
{
}

class Service2 extends Nette\Object
{
}


$builder = new DI\ContainerBuilder;
$one = $builder->addDefinition('one')
	->setClass('Service');
$child = $builder->addDefinition('child')
	->setClass('Child')
	->setAutowired(FALSE);
$two = $builder->addDefinition('two')
	->setClass('Service2');
$three = $builder->addDefinition('three')
	->setClass('Service2')
	->setAutowired(FALSE);

$container = createContainer($builder);


Assert::type('Service', $container->getByType('Service'));

Assert::null($container->getByType('Child', FALSE));

Assert::type('Service2', $container->getByType('Service2'));

Assert::exception(function () use ($container) {
	$container->getByType('Nette\Object');
}, 'Nette\DI\MissingServiceException', 'Multiple services of type Nette\Object found: one, two, container.');

Assert::null($container->getByType('unknown', FALSE));

Assert::exception(function () use ($container) {
	$container->getByType('unknown');
}, 'Nette\DI\MissingServiceException', 'Service of type unknown not found.');


Assert::same(array('one', 'child'), $container->findByType('Service'));
Assert::same(array('child'), $container->findByType('Child'));
Assert::same(array('two', 'three'), $container->findByType('Service2'));
Assert::same(array(), $container->findByType('unknown'));
