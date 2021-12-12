<?php

/**
 * Test: Nette\DI\Compiler and dependencies.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\DependencyChecker;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$compiler = new DI\Compiler;

Assert::same(
	[
		DependencyChecker::VERSION,
		[],
		[],
		[],
		[],
		'40cd750bba9870f18aada2478b24840a',
	],
	$compiler->exportDependencies()
);
Assert::false(DependencyChecker::isExpired(...$compiler->exportDependencies()));


$compiler->addDependencies(['file1', __FILE__]);
Assert::same(
	[
		DependencyChecker::VERSION,
		['file1' => false, __FILE__ => filemtime(__FILE__)],
		[],
		[],
		[],
		'40cd750bba9870f18aada2478b24840a',
	],
	$compiler->exportDependencies()
);
Assert::false(DependencyChecker::isExpired(...$compiler->exportDependencies()));


$compiler->addDependencies(['file1', null, 'file3']);
Assert::same(
	[
		DependencyChecker::VERSION,
		['file1' => false, __FILE__ => filemtime(__FILE__), 'file3' => false],
		[],
		[],
		[],
		'40cd750bba9870f18aada2478b24840a',
	],
	$compiler->exportDependencies()
);

$res = $compiler->exportDependencies();
$res[1]['file4'] = 123;
Assert::true(DependencyChecker::isExpired(...$res));
