<?php

/**
 * Test: Nette\DI\ContainerBuilder::getByType() and findByType()
 */

use Nette\DI,
	Tester\Assert;


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


Assert::null( $builder->getByType('\Service') );

$builder->prepareClassList();

Assert::same( 'one', $builder->getByType('\Service') );
Assert::null( $builder->getByType('unknown') );
Assert::exception(function() use ($builder) {
	$builder->getByType('Nette\Object');
}, 'Nette\DI\ServiceCreationException', 'Multiple services of type Nette\Object found: one, two, container');


Assert::same( array('one' => $builder->getDefinition('one')), $builder->findByType('Service') );
Assert::same(
	array('two' => $builder->getDefinition('two'), 'three' => $builder->getDefinition('three')),
	$builder->findByType('Service2')
);
Assert::same( array(), $builder->findByType('unknown') );
