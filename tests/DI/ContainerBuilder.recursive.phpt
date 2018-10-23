<?php

/**
 * Test: Nette\DI\ContainerBuilder and recursive dependencies.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
	public function __construct($obj)
	{
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setFactory('@two::get');
$builder->addDefinition('two')
	->setFactory('@one::get');

Assert::exception(function () use ($builder) {
	$container = createContainer($builder);
}, Nette\DI\ServiceCreationException::class, "Service 'two': Circular reference detected for services: one, two.");
