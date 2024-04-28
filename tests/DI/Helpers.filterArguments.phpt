<?php

/**
 * Test: Nette\DI\Helpers::filterArguments()
 */

declare(strict_types=1);

use Nette\DI\Definitions\Statement;
use Nette\DI\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same([], Helpers::filterArguments([]));

Assert::equal(
	['a', 'b', new Nette\DI\Definitions\Reference('service')],
	Helpers::filterArguments(['a', 'b', '@service']),
);

Assert::equal(
	[new Statement('class', ['a', new Nette\DI\Definitions\Reference('service')])],
	Helpers::filterArguments([new Statement('class', ['a', '@service'])]),
);
