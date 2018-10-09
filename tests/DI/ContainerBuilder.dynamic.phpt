<?php

/**
 * Test: Nette\DI\ContainerBuilder and dynamic service
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class ParentClass
{
}

class Service extends ParentClass
{
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one', new Nette\DI\Definitions\DynamicDefinition)
	->setType('ParentClass');


// compile-time

Assert::same('one', $builder->getByType('ParentClass'));

$container = createContainer($builder);

Assert::true($container->hasService('one'));
Assert::false($container->isCreated('one'));

Assert::exception(function () use ($container) {
	$container->getService('one');
}, Nette\DI\ServiceCreationException::class, "Unable to create dynamic service 'one', it must be added using addService()");


Assert::exception(function () use ($container) {
	$container->addService('one', new stdClass);
}, Nette\InvalidArgumentException::class, "Service 'one' must be instance of ParentClass, stdClass given.");

$container->addService('one', $obj = new Service);
Assert::same($obj, $container->getService('one'));

Assert::true($container->isCreated('one'));
