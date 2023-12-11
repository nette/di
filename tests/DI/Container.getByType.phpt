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
	->setType(Service::class);
$child = $builder->addDefinition('child')
	->setType(Child::class)
	->setAutowired(false);
$two = $builder->addDefinition('two')
	->setType(Service2::class);
$three = $builder->addDefinition('three')
	->setType(Service2::class)
	->setAutowired(false);

$container = createContainer($builder);


Assert::type(Service::class, $container->getByType(Service::class));

Assert::null($container->getByType(Child::class, throw: false));

Assert::type(Service2::class, $container->getByType(Service2::class));

Assert::exception(
	fn() => $container->getByType(stdClass::class),
	Nette\DI\MissingServiceException::class,
	'Multiple services of type stdClass found: one, two.',
);

Assert::null($container->getByType('unknown', throw: false));

Assert::exception(
	fn() => $container->getByType('unknown'),
	Nette\DI\MissingServiceException::class,
	"Service of type 'unknown' not found. Check the class name because it cannot be found.",
);

Assert::exception(
	fn() => $container->getByType('Exception'),
	Nette\DI\MissingServiceException::class,
	'Service of type Exception not found. Did you add it to configuration file?',
);


Assert::same(['one', 'child'], $container->findByType(Service::class));
Assert::same(['child'], $container->findByType(Child::class));
Assert::same(['two', 'three'], $container->findByType(Service2::class));
Assert::same([], $container->findByType('unknown'));
