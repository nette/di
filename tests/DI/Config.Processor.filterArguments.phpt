<?php

/**
 * Test: Nette\DI\Config\Processor::processArguments()
 */

declare(strict_types=1);

use Nette\DI\Config\Processor;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same([], Processor::processArguments([]));

Assert::same(
	['a', 'b', 3 => ['c'], [1 => 'd']],
	Processor::processArguments(['a', 'b', '...', ['c', '...'], ['...', 'd']])
);

Assert::same(
	['a', 'b', Nette\DI\ContainerBuilder::THIS_CONTAINER],
	Processor::processArguments(['a', 'b', 'Nette\DI\ContainerBuilder::THIS_CONTAINER'])
);

Assert::equal(
	['a', 'b', new Nette\DI\Definitions\Reference('service')],
	Processor::processArguments(['a', 'b', '@service'])
);

Assert::equal(
	[new Statement('class', ['a', 2 => Nette\DI\ContainerBuilder::THIS_CONTAINER])],
	Processor::processArguments([new Statement('class', ['a', '...', 'Nette\DI\ContainerBuilder::THIS_CONTAINER'])])
);
