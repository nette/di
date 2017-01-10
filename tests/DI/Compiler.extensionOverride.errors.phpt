<?php

/**
 * Test: Overriding class of service definition defined in CompilerExtension.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$compiler = new DI\Compiler;

	createContainer($compiler, '
services:
	bad:
		alteration: yes
	');
}, Nette\DI\ServiceCreationException::class, "Service 'bad': missing original definition for alteration.");
