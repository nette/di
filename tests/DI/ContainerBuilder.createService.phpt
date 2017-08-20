<?php

/**
 * Test: Nette\DI\ContainerBuilder::createService().
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType('stdClass');


$container = createContainer($builder);

$one = $container->getService('one');
$a = $container->createService('one');
$b = $container->createService('one');

Assert::type(stdClass::class, $one);
Assert::type(stdClass::class, $a);
Assert::type(stdClass::class, $b);

Assert::notSame($one, $a);
Assert::notSame($one, $b);
Assert::notSame($a, $b);
