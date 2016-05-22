<?php

/**
 * Test: Nette\DI\Compiler and dependencies.
 */

use Nette\DI;
use Nette\DI\CacheDependencies;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$compiler = new DI\Compiler;

Assert::same(
	[CacheDependencies::VERSION, [], [], [], [], '40cd750bba9870f18aada2478b24840a'],
	$compiler->exportDependencies()
);
Assert::false(CacheDependencies::isExpired(...$compiler->exportDependencies()));


$compiler->addDependencies(['file1', __FILE__]);
Assert::same(
	[CacheDependencies::VERSION, ['file1' => FALSE, __FILE__ => filemtime(__FILE__)], [], [], [], '40cd750bba9870f18aada2478b24840a'],
	$compiler->exportDependencies()
);
Assert::false(CacheDependencies::isExpired(...$compiler->exportDependencies()));


$compiler->addDependencies(['file1', NULL, 'file3']);
Assert::same(
	[CacheDependencies::VERSION, ['file1' => FALSE, __FILE__ => filemtime(__FILE__), 'file3' => FALSE], [], [], [], '40cd750bba9870f18aada2478b24840a'],
	$compiler->exportDependencies()
);

$res = $compiler->exportDependencies();
$res[1]['file4'] = 123;
Assert::true(CacheDependencies::isExpired(...$res));
