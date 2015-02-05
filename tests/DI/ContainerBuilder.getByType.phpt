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
$builder->addDefinition('one')
	->setClass('Service');
$builder->addDefinition('two')
	->setClass('Service2');
$builder->addDefinition('three')
	->setClass('Service2')
	->setAutowired(FALSE);


// compile-time
$builder->prepareClassList();

Assert::same( array('one'), $builder->findByType('Service') );
Assert::same( array('one'), $builder->findByType('Service', FALSE) );
Assert::same( array('two'), $builder->findByType('Service2') );
Assert::same( array('two', 'three'), $builder->findByType('Service2', FALSE) );
Assert::same( array(), $builder->findByType('unknown') );
Assert::same( array(), $builder->findByType('unknown', FALSE) );

Assert::same( 'one', $builder->getByType('Service') );
Assert::null( $builder->getByType('unknown') );
Assert::exception(function() use ($builder) {
	$builder->getByType('Nette\Object');
}, 'Nette\DI\ServiceCreationException', 'Multiple services of type Nette\Object found: one, two, container');


$container = createContainer($builder);

Assert::type( 'Service', $container->getByType('Service') );
Assert::null( $container->getByType('unknown', FALSE) );

Assert::same( array('one'), $container->findByType('Service') );
Assert::same( array('one'), $container->findByType('Service', FALSE) );
Assert::same( array('two'), $container->findByType('Service2') );
Assert::same( array('two', 'three'), $container->findByType('Service2', FALSE) );
Assert::same( array(), $container->findByType('unknown') );
Assert::same( array(), $container->findByType('unknown', FALSE) );

Assert::exception(function() use ($container) {
	$container->getByType('unknown');
}, 'Nette\DI\MissingServiceException', 'Service of type unknown not found.');

Assert::exception(function() use ($container) {
	$container->getByType('Nette\Object');
}, 'Nette\DI\MissingServiceException', 'Multiple services of type Nette\Object found: one, two, container.');
