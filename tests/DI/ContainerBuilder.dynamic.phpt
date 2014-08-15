<?php

/**
 * Test: Nette\DI\ContainerBuilder and dynamic service
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service extends Nette\Object
{
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setClass('Nette\Object')
	->setDynamic(TRUE);


// compile-time
$builder->prepareClassList();

Assert::same( 'one', $builder->getByType('Nette\Object') );

$container = createContainer($builder);

Assert::true( $container->hasService('one') );
Assert::false( $container->isCreated('one') );

Assert::exception(function() use ($container) {
	$container->getService('one');
}, 'Nette\DI\ServiceCreationException', "Unable to create dynamic service 'one', it must be added using addService()");


Assert::exception(function() use ($container) {
	$container->addService('one', new stdClass);
}, 'Nette\InvalidArgumentException', "Service 'one' must be instance of Nette\\Object, stdClass given.");

$container->addService('one', $obj = new Service);
Assert::same( $obj, $container->getService('one') );

Assert::true( $container->isCreated('one') );
