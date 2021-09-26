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
	public function create();
}

interface ServiceFactory2
{
	public function create();
}

$builder = new DI\ContainerBuilder;

$builder->addDefinition('aliasForFactory')
	->setFactory('@serviceFactory');

$builder->addDefinition('aliasForFactoryViaClass')
	->setFactory('@\ServiceFactory');

$builder->addFactoryDefinition('aliasedFactory')
	->setImplement(ServiceFactory::class)
	->setAutowired(false)
	->getResultDefinition()
		->setFactory('@serviceFactory');

$builder->addFactoryDefinition('aliasedFactoryViaClass')
	->setImplement(ServiceFactory::class)
	->setAutowired(false)
	->getResultDefinition()
		->setFactory('@\ServiceFactory');

$builder->addDefinition('aliasedService')
	->setFactory('@service');

$builder->addDefinition('aliasedServiceViaClass')
	->setFactory('@\Service');

$builder->addFactoryDefinition('serviceFactory')
	->setImplement(ServiceFactory::class)
	->getResultDefinition()
		->setFactory('@service');

$builder->addFactoryDefinition('serviceFactoryViaClass')
	->setImplement(ServiceFactory2::class)
	->getResultDefinition()
		->setFactory('@\Service');

$builder->addDefinition('service')
	->setType(Service::class);


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
Assert::type(Service::class, $container->getByType(Service::class));
Assert::type(ServiceFactory::class, $container->getByType(ServiceFactory::class));
Assert::type(ServiceFactory2::class, $container->getByType(ServiceFactory2::class));
