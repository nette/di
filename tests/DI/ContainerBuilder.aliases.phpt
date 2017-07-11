<?php

/**
 * Test: Nette\DI\ContainerBuilder and aliases.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
}

interface ServiceFactory
{
	function create();
}

interface ServiceFactory2
{
	function create();
}

$builder = new DI\ContainerBuilder;

$builder->addDefinition('aliasForFactory')
	->setFactory('@serviceFactory');

$builder->addDefinition('aliasForFactoryViaClass')
	->setFactory('@\ServiceFactory');

$builder->addDefinition('aliasedFactory')
	->setImplement('ServiceFactory')
	->setFactory('@serviceFactory');

$builder->addDefinition('aliasedFactoryViaClass')
	->setImplement('ServiceFactory')
	->setAutowired(false)
	->setFactory('@\ServiceFactory');

$builder->addDefinition('aliasedService')
	->setFactory('@service');

$builder->addDefinition('aliasedServiceViaClass')
	->setFactory('@\Service');

$builder->addDefinition('serviceFactory')
	->setImplement('ServiceFactory')
	->setFactory('@service');

$builder->addDefinition('serviceFactoryViaClass')
	->setImplement('ServiceFactory2')
	->setFactory('@\Service');

$builder->addDefinition('service')
	->setClass('Service');


$container = createContainer($builder);

Assert::type(Service::class, $container->getService('service'));
Assert::type(Service::class, $container->getService('aliasedService'));
Assert::type(Service::class, $container->getService('aliasedServiceViaClass'));

Assert::type(ServiceFactory::class, $container->getService('serviceFactory'));
Assert::type(ServiceFactory2::class, $container->getService('serviceFactoryViaClass'));

Assert::type(ServiceFactory::class, $container->getService('aliasedFactory'));
Assert::type(ServiceFactory::class, $container->getService('aliasedFactoryViaClass'));
Assert::type(ServiceFactory::class, $container->getService('aliasForFactory'));
Assert::type(ServiceFactory::class, $container->getService('aliasForFactoryViaClass'));

// autowiring test
Assert::type(Service::class, $container->getByType('Service'));
Assert::type(ServiceFactory::class, $container->getByType('ServiceFactory'));
Assert::type(ServiceFactory2::class, $container->getByType('ServiceFactory2'));
