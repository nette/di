<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


Assert::exception(
	fn() => check('
	search:
		in: invalid
	'),
	Nette\DI\InvalidConfigurationException::class,
	"Option 'search\u{a0}›\u{a0}default\u{a0}›\u{a0}in' must be valid directory name, 'invalid' given.",
);


Assert::exception(
	fn() => check('
	search:
		batch:
			in: []
	'),
	Nette\DI\InvalidConfigurationException::class,
	"The item 'search\u{a0}›\u{a0}batch\u{a0}›\u{a0}in' expects to be string, array given.",
);
