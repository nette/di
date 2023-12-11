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
			create: stdClass
			autowire: false
			setups: []
			foo: bar
	');
}, Nette\DI\InvalidConfigurationException::class, "Unexpected item 'services\u{a0}›\u{a0}0\u{a0}›\u{a0}autowire', did you mean 'autowired'?");
