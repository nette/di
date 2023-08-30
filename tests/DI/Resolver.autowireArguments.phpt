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
		fn($type) => $type === Test::class ? new Test : null,
	),
);

// nullable class
Assert::equal(
	[new Test],
	Resolver::autowireArguments(
		new ReflectionFunction(function (?Test $arg) {}),
		[],
		fn($type) => $type === Test::class ? new Test : null,
	),
);

// nullable optional class
Assert::equal(
	[new Test],
	Resolver::autowireArguments(
		new ReflectionFunction(function (?Test $arg = null) {}),
		[],
		fn($type) => $type === Test::class ? new Test : null,
	),
);

// nullable optional scalar
Assert::equal(
	[],
	Resolver::autowireArguments(
		new ReflectionFunction(function (?int $arg = null) {}),
		[],
		fn($type) => $type === Test::class ? new Test : null,
	),
);

// optional arguments + positional
Assert::equal(
	['b' => 'new'],
	Resolver::autowireArguments(
		new ReflectionFunction(function ($a = 1, $b = 2) {}),
		[1 => 'new'],
		function () {},
	),
);

// optional arguments + named
Assert::equal(
	['b' => 'new'],
	Resolver::autowireArguments(
		new ReflectionFunction(function ($a = 1, $b = 2) {}),
		['b' => 'new'],
		function () {},
	),
);

// optional arguments + named variadics
Assert::equal(
	['k1' => 'new1', 'k2' => 'new2'],
	Resolver::autowireArguments(
		new ReflectionFunction(function ($a = 1, ...$args) {}),
		['k1' => 'new1', 'k2' => 'new2'],
		function () {},
	),
);

// variadics
Assert::equal(
	[1, 2, 3],
	Resolver::autowireArguments(
		new ReflectionFunction(function (...$args) {}),
		[1, 2, 3],
		function () {},
	),
);

// name of variadics is ignored
Assert::equal(
	['args' => [1, 2, 3]],
	Resolver::autowireArguments(
		new ReflectionFunction(function (...$args) {}),
		['args' => [1, 2, 3]],
		function () {},
	),
);

// named parameter intentionally overwrites the indexed one (due to overwriting in the configuration)
Assert::equal(
	[2],
	Resolver::autowireArguments(
		new ReflectionFunction(function ($a) {}),
		[1, 'a' => 2],
		function () {},
	),
);

// optional union
Assert::same(
	[],
	Resolver::autowireArguments(
		new ReflectionFunction(function (stdClass|int $x = 1) {}),
		[],
		function () {},
	),
);

// named variadics
Assert::equal(
	['a' => 1, 'b' => 2, 'c' => 3],
	Resolver::autowireArguments(
		new ReflectionFunction(function (...$args) {}),
		['a' => 1, 'b' => 2, 'c' => 3],
		function () {},
	),
);
