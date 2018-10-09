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
	public function get();
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
	->setFactory('stdClass');

$builder->addDefinition('service2')
	->setAutowired(false)
	->setFactory('stdClass');

$builder->addDefinition('one', new Nette\DI\Definitions\AccessorDefinition)
	->setImplement('StdClassAccessor')
	->setReference('stdClass');

$builder->addDefinition('two', new Nette\DI\Definitions\AccessorDefinition)
	->setImplement('AnnotatedAccessor');

$builder->addDefinition('three', new Nette\DI\Definitions\AccessorDefinition)
	->setImplement('StdClassAccessor')
	->setAutowired(false)
	->setReference('@service2');

$builder->addDefinition('four')
	->setType('AccessorReceiver');


$container = createContainer($builder);

Assert::type(StdClassAccessor::class, $container->getService('one'));
Assert::same($container->getService('one')->get(), $container->getService('service'));

Assert::type(AnnotatedAccessor::class, $container->getService('two'));
Assert::same($container->getService('two')->get(), $container->getService('service'));

Assert::type(StdClassAccessor::class, $container->getService('three'));
Assert::same($container->getService('three')->get(), $container->getService('service2'));

Assert::type(AccessorReceiver::class, $container->getService('four'));
