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
	public function get(): Service;
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
