<?php

/**
 * Test: Nette\DI\Resolver::autowireArguments()
 * @phpVersion 8.2
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


// disjunctive normal form types
Assert::exception(
	fn() => Resolver::autowireArguments(
		new ReflectionFunction(function ((Foo & Test)|string $x) {}),
		[],
		function () {},
	),
	Nette\InvalidStateException::class,
	'Parameter $x in {closure}() has complex type and no default value, so its value must be specified.',
);
