<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated factories with parameters.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface StdClassFactory
{
	function create(stdClass $a, array $b, $c = null): stdClass;
}


$builder = new DI\ContainerBuilder;
$builder->addFactoryDefinition('one')
	->setImplement(StdClassFactory::class)
	->getResultDefinition()
		->setCreator(stdClass::class)
		->addSetup('$a', [$builder::literal('$a')]);

@$builder->addFactoryDefinition('two')
	->setParameters(['stdClass foo', 'array bar', 'foobar' => null]) // parameters is deprecated
	->setImplement(StdClassFactory::class)
	->getResultDefinition()
		->setCreator(stdClass::class)
		->addSetup('$a', [$builder::literal('$foo')]);

$builder->addDefinition('three')
	->setType(stdClass::class);

$builder->addDefinition('four')
	->setCreator('@one::create', [1 => [1]])
	->setAutowired(false);

$builder->addDefinition('five')
	->setCreator('@two::create', [1 => [1]])
	->setAutowired(false);


$container = createContainer($builder);

Assert::type(StdClassFactory::class, $container->getService('one'));
Assert::type(StdClassFactory::class, $container->getService('two'));

Assert::type(stdClass::class, $container->getService('four'));
Assert::same($container->getService('four')->a, $container->getService('three'));

Assert::type(stdClass::class, $container->getService('five'));
Assert::same($container->getService('five')->a, $container->getService('three'));
