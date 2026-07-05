<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Definitions\Statement::transformValues() - the single traversal hook
 * used by Helpers::expand()/filterArguments()/prefixServiceName().
 */

use Nette\DI\Definitions\Statement;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('callback transforms entity and arguments, a new instance is returned', function () {
	// like the real walkers, the callback handles arrays itself (transformValues passes
	// the whole arguments array, delegating recursion to the callback)
	$map = function ($v) use (&$map) {
		if (is_array($v)) {
			return array_map($map, $v);
		}

		return is_string($v) ? strtr($v, ['%class%' => 'Foo', '%arg%' => 'hello']) : $v;
	};

	$statement = new Statement('%class%', ['%arg%', 2]);
	$transformed = $statement->transformValues($map);

	Assert::notSame($statement, $transformed);
	Assert::same('Foo', $transformed->getEntity());
	Assert::same(['hello', 2], $transformed->arguments);
});


test('original statement is left untouched', function () {
	$statement = new Statement('%class%', ['%arg%']);
	$statement->transformValues(fn($v) => is_string($v) ? 'CHANGED' : $v);

	Assert::same('%class%', $statement->getEntity());
	Assert::same(['%arg%'], $statement->arguments);
});


test('callback receives the whole arguments array (nested handling is the callback job)', function () {
	$statement = new Statement('Foo', [1, [2, 3]]);
	$seen = [];
	$statement->transformValues(function ($v) use (&$seen) {
		$seen[] = $v;
		return $v;
	});

	// entity + the arguments array as a whole
	Assert::same('Foo', $seen[0]);
	Assert::same([1, [2, 3]], $seen[1]);
});
