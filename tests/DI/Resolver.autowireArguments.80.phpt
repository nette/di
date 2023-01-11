<?php

/**
 * Test: Nette\DI\Resolver::autowireArguments()
 * @phpVersion 8.0
 */

declare(strict_types=1);

use Nette\DI\Resolver;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Test
{
}

// union
Assert::exception(function () {
	Resolver::autowireArguments(
		new ReflectionFunction(function (stdClass|Test $x) {}),
		[],
		function () {}
	);
}, Nette\InvalidStateException::class, 'Parameter $x in {closure}() has complex type and no default value, so its value must be specified.');

// nullable union
Assert::error(function () {
	Resolver::autowireArguments(
		new ReflectionFunction(function (stdClass|Test|null $x) {}),
		[],
		function () {}
	);
}, E_USER_DEPRECATED, 'The parameter $x in {closure}() should have a declared value in the configuration.');

// optional union
Assert::same(
	[],
	Resolver::autowireArguments(
		new ReflectionFunction(function (stdClass|int $x = 1) {}),
		[],
		function () {}
	),
);

// named variadics
Assert::equal(
	['a' => 1, 'b' => 2, 'c' => 3],
	Resolver::autowireArguments(
		new ReflectionFunction(function (...$args) {}),
		['a' => 1, 'b' => 2, 'c' => 3],
		function () {}
	)
);
