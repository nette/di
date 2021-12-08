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
}

Assert::equal(
	[new Test, new Test],
	Resolver::autowireArguments(new ReflectionMethod(Test::class, 'method'), [], function ($type) {
		return $type === Test::class ? new Test : null;
	})
);

Assert::error(function () {
	Resolver::autowireArguments(new ReflectionMethod(Test::class, 'methodNullable'), [], function ($type) {
		return $type === Test::class ? new Test : null;
	});
}, E_USER_DEPRECATED, 'The parameter $nullable2 in Test::methodNullable() should have a declared value in the configuration. Value NULL is currently used.');


// variadics
Assert::equal(
	[],
	Resolver::autowireArguments(new ReflectionFunction(function (...$args) {}), [], function () {})
);

Assert::equal(
	[1, 2, 3],
	Resolver::autowireArguments(new ReflectionFunction(function (...$args) {}), [1, 2, 3], function () {})
);
