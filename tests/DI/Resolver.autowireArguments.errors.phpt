<?php

/**
 * Test: Nette\DI\Resolver::autowireArguments()
 */

declare(strict_types=1);

use Nette\DI\Resolver;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


// unknown
Assert::exception(
	fn() => Resolver::autowireArguments(
		new ReflectionFunction(function (stdClass $x) {}),
		[],
		function () {},
	),
	Nette\DI\ServiceCreationException::class,
	'Service of type stdClass required by $x in {closure}() not found. Did you add it to configuration file?',
);


// not found
Assert::exception(
	fn() => Resolver::autowireArguments(
		new ReflectionFunction(function (Foo $x) {}),
		[],
		function () {},
	),
	Nette\DI\ServiceCreationException::class,
	"Class 'Foo' required by \$x in {closure}() not found. Check the parameter type and 'use' statements.",
);


// no typehint
Assert::exception(
	fn() => Resolver::autowireArguments(
		new ReflectionFunction(function ($x) {}),
		[],
		function () {},
	),
	Nette\DI\ServiceCreationException::class,
	'Parameter $x in {closure}() has no class type or default value, so its value must be specified.',
);


// scalar
Assert::exception(
	fn() => Resolver::autowireArguments(
		new ReflectionFunction(function (int $x) {}),
		[],
		function () {},
	),
	Nette\DI\ServiceCreationException::class,
	'Parameter $x in {closure}() has no class type or default value, so its value must be specified.',
);


// nullable unknown class
Assert::error(
	fn() => Resolver::autowireArguments(
		new ReflectionFunction(function (?stdClass $arg) {}),
		[],
		fn($type) => $type === Test::class ? new Test : null,
	),
	E_USER_DEPRECATED,
	'The parameter $arg in {closure}() should have a declared value in the configuration.',
);


// nullable scalar
Assert::error(
	fn() => Resolver::autowireArguments(
		new ReflectionFunction(function (?int $arg) {}),
		[],
		fn($type) => $type === Test::class ? new Test : null,
	),
	E_USER_DEPRECATED,
	'The parameter $arg in {closure}() should have a declared value in the configuration.',
);


// non-array named variadics
Assert::exception(
	fn() => Resolver::autowireArguments(
		new ReflectionFunction(function (...$args) {}),
		['args' => 1],
		function () {},
	),
	Nette\DI\ServiceCreationException::class,
	'Parameter $args in {closure}() must be array, integer given.',
);


// bad variadics (this is actually what PHP allows)
Assert::exception(
	fn() => Resolver::autowireArguments(
		new ReflectionFunction(function (...$args) {}),
		[1, 'args' => []],
		function () {},
	),
	Nette\DI\ServiceCreationException::class,
	'Unable to pass specified arguments to {closure}%a?%.',
);


// bad variadics
if (PHP_VERSION_ID >= 80000) {
	Assert::exception(
		fn() => Resolver::autowireArguments(
			new ReflectionFunction(function ($a = 1, ...$args) {}),
			[1 => 'new1', 2 => 'new2'],
			function () {},
		),
		Nette\DI\ServiceCreationException::class,
		'Cannot use positional argument after named or omitted argument in $args in {closure}%a?%.',
	);
}
