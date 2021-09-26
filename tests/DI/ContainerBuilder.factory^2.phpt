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

interface StdClassFactoryFactory
{
	public function create(): StdClassFactory;
}


$builder = new DI\ContainerBuilder;
$builder->addFactoryDefinition('one')
	->setImplement(StdClassFactoryFactory::class)
	->setResultDefinition(new DI\Definitions\FactoryDefinition);


$container = createContainer($builder);


$one = $container->getService('one');
Assert::type(StdClassFactoryFactory::class, $one);

$factory = $one->create();
Assert::type(StdClassFactory::class, $factory);
Assert::notSame($one->create(), $one->create());

Assert::type(stdClass::class, $factory->create());
Assert::notSame($factory->create(), $factory->create());
