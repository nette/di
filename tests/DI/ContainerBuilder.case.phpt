<?php

/**
 * Test: Nette\DI\ContainerBuilder: case sensitivity
 */

declare(strict_types=1);

use Nette\DI\ContainerBuilder;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$builder = new ContainerBuilder;
	$builder->addDefinition('one');
	$builder->addDefinition('One');
}, Nette\InvalidStateException::class, "Service 'One' has the same name as 'one' in a case-insensitive manner.");
