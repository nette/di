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
	public function methodUnion(\stdClass|self $self)
	{
	}


	public function methodUnionNullable(\stdClass|self|null $nullable)
	{
	}


	public function methodUnionDefault(\stdClass|int $default = 1)
	{
	}
}


Assert::exception(function () {
	Resolver::autowireArguments(new ReflectionMethod('Test', 'methodUnion'), [], function () {});
}, Nette\InvalidStateException::class, 'Parameter $self in Test::methodUnion() has union type hint and no default value, so its value must be specified.');

Assert::same(
	[null],
	Resolver::autowireArguments(new ReflectionMethod('Test', 'methodUnionNullable'), [], function () {}),
);

Assert::same(
	[],
	Resolver::autowireArguments(new ReflectionMethod('Test', 'methodUnionDefault'), [], function () {}),
);
