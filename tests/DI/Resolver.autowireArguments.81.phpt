<?php

/**
 * Test: Nette\DI\Resolver::autowireArguments()
 * @phpVersion 8.1
 */

declare(strict_types=1);

use Nette\DI\Resolver;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Foo
{
}

class Test
{
}


// intersection
Assert::exception(function () {
	Resolver::autowireArguments(
		new ReflectionFunction(function (Foo&Test $x) {}),
		[],
		function () {}
	);
}, Nette\InvalidStateException::class, 'Parameter $x in {closure}() has complex type and no default value, so its value must be specified.');

// object as default
Assert::same(
	['b' => 10],
	Resolver::autowireArguments(
		new ReflectionFunction(function ($a = new stdClass, $b = null) {}),
		[1 => 10],
		function () {}
	),
);

// object as default with typehint
Assert::same(
	['b' => 10],
	Resolver::autowireArguments(
		new ReflectionFunction(function (stdClass $a = new stdClass, $b = null) {}),
		[1 => 10],
		function () {}
	),
);
