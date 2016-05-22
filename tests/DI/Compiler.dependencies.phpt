<?php

/**
 * Test: Nette\DI\Compiler and dependencies.
 */

use Nette\DI;
use Nette\DI\DependencyChecker;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$compiler = new DI\Compiler;

Assert::same(
	[],
	$compiler->exportDependencies()
);
Assert::false(DependencyChecker::isExpired($compiler->exportDependencies()));


$compiler->addDependencies(['file1', __FILE__]);
Assert::same(
	['file1' => FALSE, __FILE__ => filemtime(__FILE__)],
	$compiler->exportDependencies()
);
Assert::false(DependencyChecker::isExpired($compiler->exportDependencies()));


$compiler->addDependencies(['file1', NULL, 'file3']);
Assert::same(
	['file1' => FALSE, __FILE__ => filemtime(__FILE__), 'file3' => FALSE],
	$compiler->exportDependencies()
);

$res = $compiler->exportDependencies();
$res['file4'] = 123;
Assert::true(DependencyChecker::isExpired($res));
