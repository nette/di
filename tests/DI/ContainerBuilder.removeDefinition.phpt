<?php

/**
 * Test: Nette\DI\ContainerBuilder and removeDefinition.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class B extends stdClass
{}

class A extends B
{}


$builder = new DI\ContainerBuilder;

$builder->addDefinition('one')
	->setClass('stdClass');

$builder->addDefinition('two')
	->setClass('stdClass');

$builder->addDefinition('three')
	->setClass('stdClass')
	->setAutowired(FALSE);

$builder->addDefinition('four')
	->setClass('A');

$builder->prepareClassList();

Assert::exception(function () use ($builder) {
	$builder->getByType('stdClass');
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type stdClass found: one, two, four');

Assert::count(4, $builder->findByType('stdClass'));


$builder->removeDefinition('one');
$builder->removeDefinition('four');

Assert::same('two', $builder->getByType('stdClass'));

Assert::count(2, $builder->findByType('stdClass'));


$builder->removeDefinition('three');

Assert::count(1, $builder->findByType('stdClass'));


$builder->addDefinition('one')
	->setClass('stdClass');

Assert::count(2, $builder->findByType('stdClass'));
