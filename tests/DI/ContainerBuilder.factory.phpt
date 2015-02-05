<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated factories.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface StdClassFactory
{
	function create();
}

interface AnnotatedFactory
{
	/** @return stdClass */
	function create();
}

class FactoryReceiver
{
	function __construct(StdClassFactory $factory)
	{}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setImplement('StdClassFactory')
	->setFactory('stdClass');

$builder->addDefinition('two')
	->setImplement('AnnotatedFactory');

$builder->addDefinition('three')
	->setClass('FactoryReceiver');

$builder->addDefinition('four')
	->setFactory('FactoryReceiver', array('@one'));


$container = createContainer($builder);

Assert::type( 'StdClassFactory', $container->getService('one') );
Assert::type( 'stdClass', $container->getService('one')->create() );
Assert::notSame( $container->getService('one')->create(), $container->getService('one')->create() );

Assert::type( 'AnnotatedFactory', $container->getService('two') );
Assert::type( 'stdClass', $container->getService('two')->create() );
Assert::notSame( $container->getService('two')->create(), $container->getService('two')->create() );

Assert::type( 'FactoryReceiver', $container->getService('three') );

Assert::type( 'FactoryReceiver', $container->getService('four') );
