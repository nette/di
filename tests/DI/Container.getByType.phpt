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

class Service2 extends Nette\Object
{
}


$builder = new DI\ContainerBuilder;
$one = $builder->addDefinition('one')
	->setClass('Service');
$two = $builder->addDefinition('two')
	->setClass('Service2');
$three = $builder->addDefinition('three')
	->setClass('Service2')
	->setAutowired(FALSE);

$container = createContainer($builder);

Assert::type('Service', $container->getByType('Service'));
Assert::null($container->getByType('unknown', FALSE));

Assert::exception(function () use ($container) {
	$container->getByType('unknown');
}, Nette\DI\MissingServiceException::class, 'Service of type unknown not found.');

Assert::exception(function () use ($container) {
	$container->getByType(Nette\Object::class);
}, Nette\DI\MissingServiceException::class, 'Multiple services of type Nette\Object found: one, two, container.');


Assert::same(['one'], $container->findByType('Service'));
Assert::same(['two', 'three'], $container->findByType('Service2'));
Assert::same([], $container->findByType('unknown'));
