<?php

/**
 * Test: Nette\DI\ContainerBuilder and removeDefinition.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class B extends stdClass
{
}

class A extends B
{
}


$builder = new DI\ContainerBuilder;

$builder->addDefinition('one')
	->setType(stdClass::class);

$builder->addDefinition('two')
	->setType(stdClass::class);

$builder->addDefinition('three')
	->setType(stdClass::class)
	->setAutowired(false);

$builder->addDefinition('four')
	->setType('A');


Assert::exception(function () use ($builder) {
	$builder->getByType(stdClass::class);
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type stdClass found: four, one, two');

Assert::count(4, $builder->findByType(stdClass::class));


$builder->removeDefinition('one');
$builder->removeDefinition('four');

Assert::same('two', $builder->getByType(stdClass::class));

Assert::count(2, $builder->findByType(stdClass::class));


$builder->removeDefinition('three');

Assert::count(1, $builder->findByType(stdClass::class));


$builder->addDefinition('one')
	->setType(stdClass::class);

Assert::count(2, $builder->findByType(stdClass::class));
