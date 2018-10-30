<?php

/**
 * Test: Nette\DI\Compiler: exception with uknown definition keys.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::throws(function () {
	createContainer(new DI\Compiler, '
	services:
		-
			factory: stdClass
			autowire: false
			setups: []
			foo: bar
	');
}, Nette\InvalidStateException::class, "Service '1': Unknown key 'autowire', 'setups', 'foo' in definition of service, did you mean 'autowired', 'setup'?");
