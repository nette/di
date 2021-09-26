<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated factories.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface StdClassAccessor
{
	public function get(): stdClass;
}

interface StdClassAccessorAccessor
{
	public function get(): StdClassAccessor;
}


$builder = new DI\ContainerBuilder;
$builder->addAccessorDefinition('one')
	->setImplement(StdClassAccessorAccessor::class);

$builder->addAccessorDefinition('two')
	->setImplement(StdClassAccessor::class);

$builder->addDefinition('three')
	->setClass(stdClass::class);


$container = createContainer($builder);


$one = $container->getService('one');
Assert::type(StdClassAccessorAccessor::class, $one);

$accessor = $one->get();
Assert::type(StdClassAccessor::class, $accessor);
Assert::same($one->get(), $one->get());

Assert::type(stdClass::class, $accessor->get());
Assert::same($accessor->get(), $accessor->get());
