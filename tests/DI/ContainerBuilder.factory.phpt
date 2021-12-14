<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated factories.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface StdClassFactory
{
	public function create(): stdClass;
}

class FactoryReceiver
{
	public function __construct(StdClassFactory $factory)
	{
	}
}


$builder = new DI\ContainerBuilder;
$builder->addFactoryDefinition('one')
	->setImplement(StdClassFactory::class)
	->getResultDefinition()
		->setCreator(stdClass::class);

$builder->addDefinition('three')
	->setType(FactoryReceiver::class);

$builder->addDefinition('four')
	->setCreator(FactoryReceiver::class, ['@one']);


$container = createContainer($builder);

Assert::type(StdClassFactory::class, $container->getService('one'));
Assert::type(stdClass::class, $container->getService('one')->create());
Assert::notSame($container->getService('one')->create(), $container->getService('one')->create());

Assert::type(FactoryReceiver::class, $container->getService('three'));

Assert::type(FactoryReceiver::class, $container->getService('four'));
