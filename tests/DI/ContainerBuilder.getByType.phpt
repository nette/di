<?php

/**
 * Test: Nette\DI\ContainerBuilder::getByType() and findByType()
 */

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




Assert::same('one', $builder->getByType('\Service'));

Assert::null($builder->getByType('Child'));

Assert::exception(function () use ($builder) {
	$builder->getByType('Child', TRUE);
}, Nette\DI\MissingServiceException::class, "Service of type 'Child' not found.");

Assert::same('two', $builder->getByType('Service2'));

Assert::exception(function () use ($builder) {
	$builder->getByType(stdClass::class);
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type stdClass found: one, two');

Assert::exception(function () use ($builder) {
	$builder->getByType('unknown', TRUE);
}, Nette\DI\MissingServiceException::class, "Service of type 'unknown' not found.");

Assert::null($builder->getByType('unknown'));


Assert::same([
	'one' => $builder->getDefinition('one'),
	'child' =>  $builder->getDefinition('child'),
], $builder->findByType('Service'));

Assert::same([
	'child' =>  $builder->getDefinition('child'),
], $builder->findByType('Child'));

Assert::same(
	['two' => $builder->getDefinition('two'), 'three' => $builder->getDefinition('three')],
	$builder->findByType('Service2')
);
Assert::same([], $builder->findByType('unknown'));
