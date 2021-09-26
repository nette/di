<?php

/**
 * Test: Nette\DI\ContainerBuilder.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


function create(): stdClass
{
	return new stdClass;
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setFactory('::create');


$container = createContainer($builder);

Assert::type(stdClass::class, $container->getService('one'));
