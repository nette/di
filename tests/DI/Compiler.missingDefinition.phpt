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
}, Nette\InvalidStateException::class, 'Factory and type are missing in definition of service.');


Assert::throws(function () {
	createContainer(new DI\Compiler, '
	services:
		foo:
	');
}, Nette\InvalidStateException::class, "(Service 'foo')
Factory and type are missing in definition of service.");
