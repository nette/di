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
	public function method(self $class, self $self, Undefined $nullable1 = null, int $nullable2 = null)
	{
	}


	public function methodNullable(?self $class, ?self $self, ?Undefined $nullable1, ?int $nullable2)
	{
	}


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

Assert::equal(
	[new Test, new Test],
	Resolver::autowireArguments(new ReflectionMethod('Test', 'method'), [], fn($type) => $type === 'Test' ? new Test : null),
);

Assert::equal(
	[new Test, new Test, null, null],
	Resolver::autowireArguments(new ReflectionMethod('Test', 'methodNullable'), [], fn($type) => $type === 'Test' ? new Test : null),
);

Assert::exception(function () {
	Resolver::autowireArguments(new ReflectionMethod('Test', 'methodUnion'), [], function () {});
}, Nette\InvalidStateException::class, 'Parameter $self in Test::methodUnion() has union type and no default value, so its value must be specified.');

Assert::same(
	[null],
	Resolver::autowireArguments(new ReflectionMethod('Test', 'methodUnionNullable'), [], function () {}),
);

Assert::same(
	[],
	Resolver::autowireArguments(new ReflectionMethod('Test', 'methodUnionDefault'), [], function () {}),
);
