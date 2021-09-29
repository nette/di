<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::throws(function () {
	createContainer(new DI\Compiler, '
	services:
		-
	');
}, Nette\InvalidStateException::class, "Service '0': Empty definition.");


Assert::throws(function () {
	createContainer(new DI\Compiler, '
	services:
		foo:
	');
}, Nette\InvalidStateException::class, "Service 'foo': Empty definition.");
