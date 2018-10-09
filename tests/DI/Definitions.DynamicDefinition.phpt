<?php

/**
 * Test: DynamicDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\DynamicDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$def = new DynamicDefinition;
	$def->setType('Foo');
}, Nette\InvalidArgumentException::class, "Service '': Class or interface 'Foo' not found.");
