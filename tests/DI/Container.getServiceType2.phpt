<?php

/**
 * Test: Nette\DI\Container::getServiceType()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
}

interface Accessor
{
	public function get(): Service;
}

interface Factory
{
	public function create(): Service;
}


$builder = new DI\ContainerBuilder;
$one = $builder->addDefinition('one')
	->setType(Service::class);
$child = $builder->addAccessorDefinition('acc')
	->setImplement(Accessor::class);
$two = $builder->addFactoryDefinition('fac')
	->setImplement(Factory::class);

$container = createContainer($builder);


Assert::same(Service::class, $container->getServiceType('one'));
Assert::same(Accessor::class, $container->getServiceType('acc'));
Assert::same(Factory::class, $container->getServiceType('fac'));
