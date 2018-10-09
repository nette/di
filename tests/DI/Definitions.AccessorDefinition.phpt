<?php

/**
 * Test: AccessorDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\AccessorDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$def = new AccessorDefinition;
	$def->setType('Foo');
}, Nette\MemberAccessException::class);

Assert::exception(function () {
	$def = new AccessorDefinition;
	$def->setImplement('Foo');
}, Nette\InvalidArgumentException::class, "Service '': Interface 'Foo' not found.");

Assert::exception(function () {
	$def = new AccessorDefinition;
	$def->setImplement('stdClass');
}, Nette\InvalidArgumentException::class, "Service '': Interface 'stdClass' not found.");
