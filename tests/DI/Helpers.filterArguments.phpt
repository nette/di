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

Assert::same(
	['a', 'b', 4 => ['c'], [1 => 'd']],
	Helpers::filterArguments(['a', 'b', '...', '_', ['c', '...'], ['...', 'd']])
);

Assert::same(
	['a', 'b', Nette\DI\ContainerBuilder::THIS_CONTAINER],
	Helpers::filterArguments(['a', 'b', 'Nette\DI\ContainerBuilder::THIS_CONTAINER'])
);

Assert::equal(
	['a', 'b', new Nette\DI\Definitions\Reference('service')],
	Helpers::filterArguments(['a', 'b', '@service'])
);

Assert::equal(
	[new Statement('class', ['a', 2 => Nette\DI\ContainerBuilder::THIS_CONTAINER])],
	Helpers::filterArguments([new Statement('class', ['a', '...', 'Nette\DI\ContainerBuilder::THIS_CONTAINER'])])
);
