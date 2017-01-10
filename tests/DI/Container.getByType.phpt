<?php

/**
 * Test: Nette\DI\Container::getByType() and findByType()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service extends stdClass
{
}

class Child extends Service
{
}

class Service2 extends stdClass
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


Assert::type(Service::class, $container->getByType('Service'));

Assert::null($container->getByType('Child', FALSE));

Assert::type(Service2::class, $container->getByType('Service2'));

Assert::exception(function () use ($container) {
	$container->getByType(stdClass::class);
}, Nette\DI\MissingServiceException::class, 'Multiple services of type stdClass found: one, two.');

Assert::null($container->getByType('unknown', FALSE));

Assert::exception(function () use ($container) {
	$container->getByType('unknown');
}, Nette\DI\MissingServiceException::class, 'Service of type unknown not found.');


Assert::same(['one', 'child'], $container->findByType('Service'));
Assert::same(['child'], $container->findByType('Child'));
Assert::same(['two', 'three'], $container->findByType('Service2'));
Assert::same([], $container->findByType('unknown'));
