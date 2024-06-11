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
		DependencyChecker::Version,
		[],
		[],
		[],
		[],
		'91a436edb651f369cac5551729145e16',
	],
	$compiler->exportDependencies(),
);
Assert::false(DependencyChecker::isExpired(...$compiler->exportDependencies()));


$compiler->addDependencies(['file1', __FILE__]);
Assert::same(
	[
		DependencyChecker::Version,
		['file1' => false, __FILE__ => filemtime(__FILE__)],
		[],
		[],
		[],
		'91a436edb651f369cac5551729145e16',
	],
	$compiler->exportDependencies(),
);
Assert::false(DependencyChecker::isExpired(...$compiler->exportDependencies()));


$compiler->addDependencies(['file1', null, 'file3']);
Assert::same(
	[
		DependencyChecker::Version,
		['file1' => false, __FILE__ => filemtime(__FILE__), 'file3' => false],
		[],
		[],
		[],
		'91a436edb651f369cac5551729145e16',
	],
	$compiler->exportDependencies(),
);

$res = $compiler->exportDependencies();
$res[1]['file4'] = 123;
Assert::true(DependencyChecker::isExpired(...$res));


// test serialization of parameters
class NotSerializable
{
	public function __sleep()
	{
		throw new Exception;
	}
}


class Dep1
{
	public function f($a = new NotSerializable)
	{
	}
}

$compiler->addDependencies([new ReflectionClass(Dep1::class)]);
Assert::same(
	[
		DependencyChecker::Version,
		['file1' => false, __FILE__ => filemtime(__FILE__), 'file3' => false],
		[__FILE__ => filemtime(__FILE__)],
		['Dep1'],
		[],
		'54d3c04c0d3d52db5d38d1f0f63b9f5d',
	],
	$compiler->exportDependencies(),
);

Assert::false(DependencyChecker::isExpired(...$compiler->exportDependencies()));
