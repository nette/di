<?php

/**
 * Test: Nette\DI\ContainerBuilder::createService().
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setClass('stdClass');


$container = createContainer($builder);

$one = $container->getService('one');
$a = $container->createService('one');
$b = $container->createService('one');

Assert::type( 'stdClass', $one );
Assert::type( 'stdClass', $a );
Assert::type( 'stdClass', $b );

Assert::notSame( $one, $a );
Assert::notSame( $one, $b );
Assert::notSame( $a, $b );
