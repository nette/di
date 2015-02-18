<?php

/**
 * Test: Nette\DI\Compiler and config.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$compiler = new DI\Compiler;

Assert::same(
	array(),
	$compiler->getConfig()
);


$compiler->addConfig(array(
	'item1' => 1,
));

Assert::same(
	array(
		'item1' => 1,
	),
	$compiler->getConfig()
);


$compiler->loadConfig(Tester\FileMock::create('
item1: 11
item2: 2
', 'neon'));

Assert::same(
	array(
		'item1' => 11,
		'item2' => 2,
	),
	$compiler->getConfig()
);
