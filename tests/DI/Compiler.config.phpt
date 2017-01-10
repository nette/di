<?php

/**
 * Test: Nette\DI\Compiler and config.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$compiler = new DI\Compiler;

Assert::same(
	[],
	$compiler->getConfig()
);


$compiler->addConfig([
	'item1' => 1,
]);

Assert::same(
	[
		'item1' => 1,
	],
	$compiler->getConfig()
);


$compiler->loadConfig(Tester\FileMock::create('
item1: 11
item2: 2
', 'neon'));

Assert::same(
	[
		'item1' => 11,
		'item2' => 2,
	],
	$compiler->getConfig()
);
