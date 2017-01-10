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
	function create(stdClass $a, array $b, $c = NULL);
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setImplement('StdClassFactory')
	->setFactory('stdClass')
	->addSetup('$a', [$builder::literal('$a')]);

$builder->addDefinition('two')
	->setParameters(['stdClass foo', 'array bar', 'foobar' => NULL])
	->setImplement('StdClassFactory')
	->setFactory('stdClass')
	->addSetup('$a', [$builder::literal('$foo')]);

$builder->addDefinition('three')
	->setClass('stdClass');

$builder->addDefinition('four')
	->setFactory('@one::create', [1 => [1]])
	->setAutowired(FALSE);

$builder->addDefinition('five')
	->setFactory('@two::create', [1 => [1]])
	->setAutowired(FALSE);


$container = createContainer($builder);

Assert::type(StdClassFactory::class, $container->getService('one'));
Assert::type(StdClassFactory::class, $container->getService('two'));

Assert::type(stdClass::class, $container->getService('four'));
Assert::same($container->getService('four')->a, $container->getService('three'));

Assert::type(stdClass::class, $container->getService('five'));
Assert::same($container->getService('five')->a, $container->getService('three'));
