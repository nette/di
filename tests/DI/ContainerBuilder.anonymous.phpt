<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;
$builder->addDefinition('1')
	->setFactory(stdClass::class);

$builder->addDefinition(null)
	->setFactory(stdClass::class);

$builder->addDefinition(null)
	->setFactory(stdClass::class);


$container = createContainer($builder);

Assert::type(stdClass::class, $container->getService('1'));
Assert::type(stdClass::class, $container->getService('01'));
Assert::type(stdClass::class, $container->getService('02'));
