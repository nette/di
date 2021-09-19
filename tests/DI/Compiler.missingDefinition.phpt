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
}, Nette\DI\ServiceCreationException::class, '[Service ?]
Factory and type are missing in definition of service.');


Assert::throws(function () {
	createContainer(new DI\Compiler, '
	services:
		foo:
	');
}, Nette\DI\ServiceCreationException::class, "[Service 'foo']
Factory and type are missing in definition of service.");
