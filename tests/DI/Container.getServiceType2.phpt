<?php

/**
 * Test: Nette\DI\Container::getServiceType()
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
}

interface Accessor
{
	/** @return Service */
	public function get();
}

interface Factory
{
	/** @return Service */
	public function create();
}


$builder = new DI\ContainerBuilder;
$one = $builder->addDefinition('one')
	->setType('Service');
$child = $builder->addDefinition('acc')
	->setImplement('Accessor');
$two = $builder->addDefinition('fac')
	->setImplement('Factory');

$container = createContainer($builder);


Assert::same('Service', $container->getServiceType('one'));
Assert::same('Accessor', $container->getServiceType('acc'));
Assert::same('Factory', $container->getServiceType('fac'));
