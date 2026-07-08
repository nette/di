<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler and dependencies.
 */

use Nette\DI;
use Nette\DI\Compiler\DependencyChecker;
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
	public function __serialize()
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
		'8d641168cc42c45a2b1add7156c7bcbd',
	],
	$compiler->exportDependencies(),
);

Assert::false(DependencyChecker::isExpired(...$compiler->exportDependencies()));


// internal classes have no file
class Dep2 extends ArrayObject
{
}

$compiler = new DI\Compiler;
$compiler->addDependencies([new ReflectionClass(Dep2::class)]);
Assert::same([__FILE__ => filemtime(__FILE__)], $compiler->exportDependencies()[2]);
