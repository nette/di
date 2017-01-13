<?php

/**
 * Test: Nette\DI\ContainerBuilder and aliases.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{}

interface ServiceFactory
{
	function create();
}

interface ServiceFactory2
{
	function create();
}

$builder = new DI\ContainerBuilder;

$builder->addDefinition('serviceFactory')
	->setImplement('ServiceFactory')
	->setFactory('@service');

$builder->addDefinition('serviceFactoryViaClass')
	->setImplement('ServiceFactory2')
	->setFactory('@\Service');

$builder->addDefinition('service')
	->setClass('Foo');


$builder->addAlias('aliased.service', 'service');
$builder->addAlias('aliased.serviceFactory', 'serviceFactory');
$builder->addAlias('aliased.serviceFactoryViaClass', 'serviceFactoryViaClass');
$builder->addAlias('aliased.serviceToRemove', 'service');

Assert::same([
	'aliased.service' => 'service',
	'aliased.serviceFactory' => 'serviceFactory',
	'aliased.serviceFactoryViaClass' => 'serviceFactoryViaClass',
	'aliased.serviceToRemove' => 'service',
], $builder->getAliases());

$builder->removeAlias('aliased.serviceToRemove');

Assert::same([
	'aliased.service' => 'service',
	'aliased.serviceFactory' => 'serviceFactory',
	'aliased.serviceFactoryViaClass' => 'serviceFactoryViaClass',
], $builder->getAliases());

// Access to service definition using alias
Assert::true($builder->hasDefinition('aliased.service'));
Assert::same($builder->getDefinition('service'), $builder->getDefinition('aliased.service'));

// Replace service definition using alias
$builder->removeDefinition('aliased.service');
Assert::false($builder->hasDefinition('aliased.service'));
$builder->addDefinition('aliased.service')
	->setClass('Service');


$container = createContainer($builder);

Assert::type(Service::class, $container->getService('service'));
Assert::type(Service::class, $container->getService('aliased.service'));
Assert::same($container->getService('service'), $container->getService('aliased.service'));

Assert::type(ServiceFactory::class, $container->getService('serviceFactory'));
Assert::type(ServiceFactory::class, $container->getService('aliased.serviceFactory'));

Assert::type(ServiceFactory2::class, $container->getService('aliased.serviceFactoryViaClass'));
Assert::type(ServiceFactory2::class, $container->getService('serviceFactoryViaClass'));

// autowiring test
Assert::type(Service::class, $container->getByType('Service'));
Assert::type(ServiceFactory::class, $container->getByType('ServiceFactory'));
Assert::type(ServiceFactory2::class, $container->getByType('ServiceFactory2'));
