<?php

/**
 * Test: Nette\DI\Resolver::autowireArguments()
 */

declare(strict_types=1);

use Nette\DI\Resolver;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Test
{
}


// class
Assert::equal(
	[new Test],
	Resolver::autowireArguments(
		new ReflectionFunction(function (Test $arg) {}),
		[],
		function ($type) { return $type === Test::class ? new Test : null; }
	)
);

// nullable class
Assert::equal(
	[new Test],
	Resolver::autowireArguments(
		new ReflectionFunction(function (?Test $arg) {}),
		[],
		function ($type) { return $type === Test::class ? new Test : null; }
	)
);

// nullable unknown class
Assert::equal(
	[null],
	Resolver::autowireArguments(
		new ReflectionFunction(function (?stdClass $arg) {}),
		[],
		function ($type) { return $type === Test::class ? new Test : null; }
	)
);

// nullable scalar
Assert::equal(
	[null],
	Resolver::autowireArguments(
		new ReflectionFunction(function (?int $arg) {}),
		[],
		function ($type) { return $type === Test::class ? new Test : null; }
	)
);

// nullable optional class
Assert::equal(
	[new Test],
	Resolver::autowireArguments(
		new ReflectionFunction(function (?Test $arg = null) {}),
		[],
		function ($type) { return $type === Test::class ? new Test : null; }
	)
);

// nullable optional scalar
Assert::equal(
	[],
	Resolver::autowireArguments(
		new ReflectionFunction(function (?int $arg = null) {}),
		[],
		function ($type) { return $type === Test::class ? new Test : null; }
	)
);

// optional arguments + positional
Assert::equal(
	[1, 'new'],
	Resolver::autowireArguments(
		new ReflectionFunction(function ($a = 1, $b = 2) {}),
		[1 => 'new'],
		function () {}
	)
);

// optional arguments + named
Assert::equal(
	[1, 'new'],
	Resolver::autowireArguments(
		new ReflectionFunction(function ($a = 1, $b = 2) {}),
		['b' => 'new'],
		function () {}
	)
);

// optional arguments + variadics
Assert::equal(
	[1, 'new1', 'new2'],
	Resolver::autowireArguments(
		new ReflectionFunction(function ($a = 1, ...$args) {}),
		[1 => 'new1', 2 => 'new2'],
		function () {}
	)
);

// optional arguments + variadics
Assert::equal(
	['new', 'new1', 'new2'],
	Resolver::autowireArguments(
		new ReflectionFunction(function ($a = 1, ...$args) {}),
		['a' => 'new', 1 => 'new1', 2 => 'new2'],
		function () {}
	)
);

// variadics as items
Assert::equal(
	[1, 2, 3],
	Resolver::autowireArguments(
		new ReflectionFunction(function (...$args) {}),
		[1, 2, 3],
		function () {}
	)
);

// variadics as array
Assert::equal(
	[1, 2, 3],
	Resolver::autowireArguments(
		new ReflectionFunction(function (...$args) {}),
		['args' => [1, 2, 3]],
		function () {}
	)
);

// named parameter intentionally overwrites the indexed one (due to overwriting in the configuration)
Assert::equal(
	[2],
	Resolver::autowireArguments(
		new ReflectionFunction(function ($a) {}),
		[1, 'a' => 2],
		function () {}
	)
);
