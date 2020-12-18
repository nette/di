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
}, Nette\DI\InvalidConfigurationException::class, "Unexpected item 'services › 0 › autowire', did you mean 'autowired'?");
