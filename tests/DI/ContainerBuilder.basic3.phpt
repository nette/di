<?php

/**
 * Test: Nette\DI\ContainerBuilder.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


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

Assert::type(stdClass::class, $container->getService('one'));
