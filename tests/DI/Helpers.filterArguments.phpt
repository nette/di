<?php

/**
 * Test: Nette\DI\Helpers::filterArguments()
 */

declare(strict_types=1);

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Statement;
use Nette\DI\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same([], Helpers::filterArguments([]));

Assert::equal(
	['a', 'b', ContainerBuilder::literal('Nette\DI\ContainerBuilder::ThisContainer')],
	Helpers::filterArguments(['a', 'b', 'Nette\DI\ContainerBuilder::ThisContainer'])
);

Assert::equal(
	['a', 'b', new Nette\DI\Definitions\Reference('service')],
	Helpers::filterArguments(['a', 'b', '@service'])
);

Assert::equal(
	[new Statement('class', ['a', ContainerBuilder::literal('Nette\DI\ContainerBuilder::ThisContainer')])],
	Helpers::filterArguments([new Statement('class', ['a', 'Nette\DI\ContainerBuilder::ThisContainer'])])
);
