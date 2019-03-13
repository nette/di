<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


Assert::exception(function () {
	check('
	search:
		in: invalid
	');
}, Nette\DI\InvalidConfigurationException::class, "Option 'search › default › in' must be valid directory name, 'invalid' given.");


Assert::exception(function () {
	check('
	search:
		batch:
			in: []
	');
}, Nette\DI\InvalidConfigurationException::class, "Option 'search › batch › in' must be valid directory name, array given.");
