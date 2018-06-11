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
	->setType('stdClass');

$builder->addDefinition('two')
	->setType('stdClass');

$builder->addDefinition('three')
	->setType('stdClass')
	->setAutowired(false);

$builder->addDefinition('four')
	->setType('A');


Assert::exception(function () use ($builder) {
	$builder->getByType('stdClass');
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type stdClass found: four, one, two');

Assert::count(4, $builder->findByType('stdClass'));


$builder->removeDefinition('one');
$builder->removeDefinition('four');

Assert::same('two', $builder->getByType('stdClass'));

Assert::count(2, $builder->findByType('stdClass'));


$builder->removeDefinition('three');

Assert::count(1, $builder->findByType('stdClass'));


$builder->addDefinition('one')
	->setType('stdClass');

Assert::count(2, $builder->findByType('stdClass'));
