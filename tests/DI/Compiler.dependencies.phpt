<?php

/**
 * Test: Nette\DI\Compiler and dependencies.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$compiler = new DI\Compiler;

Assert::same(
	array(),
	$compiler->getDependencies()
);

$compiler->addDependencies(array('file1', 'file2'));

Assert::same(
	array('file1', 'file2'),
	$compiler->getDependencies()
);

$compiler->addDependencies(array('file1', NULL, 'file3'));

Assert::same(
	array('file1', 'file2', 'file3'),
	$compiler->getDependencies()
);
