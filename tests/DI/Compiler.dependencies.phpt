<?php

/**
 * Test: Nette\DI\Compiler and dependencies.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$compiler = new DI\Compiler;

Assert::same(
	[],
	$compiler->getDependencies()
);

$compiler->addDependencies(['file1', 'file2']);

Assert::same(
	['file1', 'file2'],
	$compiler->getDependencies()
);

$compiler->addDependencies(['file1', NULL, 'file3']);

Assert::same(
	['file1', 'file2', 'file3'],
	$compiler->getDependencies()
);
