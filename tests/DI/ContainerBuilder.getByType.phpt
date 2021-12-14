<?php

/**
 * Test: Nette\DI\ContainerBuilder::getByType() and findByType()
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




Assert::same('one', $builder->getByType('\Service'));

Assert::null($builder->getByType(Child::class));

Assert::exception(function () use ($builder) {
	$builder->getByType(Child::class, true);
}, Nette\DI\MissingServiceException::class, 'Service of type Child not found. Did you add it to configuration file?');

Assert::same('two', $builder->getByType(Service2::class));

Assert::exception(function () use ($builder) {
	$builder->getByType(stdClass::class);
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type stdClass found: one, two');

Assert::exception(function () use ($builder) {
	$builder->getByType('unknown', true);
}, Nette\DI\MissingServiceException::class, "Service of type 'unknown' not found. Check the class name because it cannot be found.");

Assert::null($builder->getByType('unknown'));


Assert::same([
	'one' => $builder->getDefinition('one'),
	'child' => $builder->getDefinition('child'),
], $builder->findByType(Service::class));

Assert::same([
	'child' => $builder->getDefinition('child'),
], $builder->findByType(Child::class));

Assert::same(
	['two' => $builder->getDefinition('two'), 'three' => $builder->getDefinition('three')],
	$builder->findByType(Service2::class),
);
Assert::same([], $builder->findByType('unknown'));
