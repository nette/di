<?php

/**
 * Test: Nette\DI\Config\Processor::filterArguments()
 */

declare(strict_types=1);

use Nette\DI\Config\Processor;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same([], Processor::filterArguments([]));

Assert::same(
	['a', 'b', 3 => ['c'], [1 => 'd']],
	Processor::filterArguments(['a', 'b', '...', ['c', '...'], ['...', 'd']])
);

Assert::same(
	['a', 'b', Nette\DI\ContainerBuilder::THIS_CONTAINER],
	Processor::filterArguments(['a', 'b', 'Nette\DI\ContainerBuilder::THIS_CONTAINER'])
);

Assert::equal(
	[new Statement('class', ['a', 2 => Nette\DI\ContainerBuilder::THIS_CONTAINER])],
	Processor::filterArguments([new Statement('class', ['a', '...', 'Nette\DI\ContainerBuilder::THIS_CONTAINER'])])
);
