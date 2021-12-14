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
}, Nette\InvalidStateException::class, 'Parameter $x in {closure}%a?% has intersection type, so its value must be specified.');
