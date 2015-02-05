<?php

/**
 * Test: Nette\DI\ContainerBuilder and Container: getByType()
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


// compile-time
$builder->prepareClassList();

Assert::same( 'one', $builder->getByType('\Service') );
Assert::null( $builder->getByType('unknown') );
Assert::exception(function() use ($builder) {
	$builder->getByType('Nette\Object');
}, 'Nette\DI\ServiceCreationException', 'Multiple services of type Nette\Object found: one, two, container');


$container = createContainer($builder);

Assert::type( 'Service', $container->getByType('Service') );
Assert::null( $container->getByType('unknown', FALSE) );

Assert::same( array('one'), $container->findByType('Service') );
Assert::same( array('two', 'three'), $container->findByType('Service2') );
Assert::same( array(), $container->findByType('unknown') );

Assert::exception(function() use ($container) {
	$container->getByType('unknown');
}, 'Nette\DI\MissingServiceException', 'Service of type unknown not found.');

Assert::exception(function() use ($container) {
	$container->getByType('Nette\Object');
}, 'Nette\DI\MissingServiceException', 'Multiple services of type Nette\Object found: one, two, container.');
