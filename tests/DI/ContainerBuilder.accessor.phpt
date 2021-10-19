<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated accessors.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface StdClassAccessor
{
	public function get(): stdClass;
}

interface AnnotatedAccessor
{
	/** @return stdClass */
	public function get();
}

class AccessorReceiver
{
	public $accessor;


	public function __construct(StdClassAccessor $accessor)
	{
		$this->accessor = $accessor;
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('service')
	->setFactory(stdClass::class);

$builder->addDefinition('service2')
	->setAutowired(false)
	->setFactory(stdClass::class);

$builder->addAccessorDefinition('one')
	->setImplement(StdClassAccessor::class)
	->setReference(stdClass::class);

@$builder->addAccessorDefinition('two')
	->setImplement(AnnotatedAccessor::class); // missing type triggers warning

$builder->addAccessorDefinition('three')
	->setImplement(StdClassAccessor::class)
	->setAutowired(false)
	->setReference('@service2');

$builder->addDefinition('four')
	->setType(AccessorReceiver::class);


$container = createContainer($builder);

Assert::type(StdClassAccessor::class, $container->getService('one'));
Assert::same($container->getService('one')->get(), $container->getService('service'));

Assert::type(AnnotatedAccessor::class, $container->getService('two'));
Assert::same($container->getService('two')->get(), $container->getService('service'));

Assert::type(StdClassAccessor::class, $container->getService('three'));
Assert::same($container->getService('three')->get(), $container->getService('service2'));

Assert::type(AccessorReceiver::class, $container->getService('four'));
