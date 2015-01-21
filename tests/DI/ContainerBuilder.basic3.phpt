<?php

/**
 * Test: Nette\DI\ContainerBuilder.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


/** @return stdClass */
function create()
{
	return new stdClass;
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setFactory('::create');


$container = createContainer($builder);

Assert::type( 'stdClass', $container->getService('one') );
