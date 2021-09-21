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
	Resolver::autowireArguments(new ReflectionMethod('Test', 'method'), [], fn($type) => $type === 'Test' ? new Test : null),
);

Assert::equal(
	[new Test, new Test, null, null],
	Resolver::autowireArguments(new ReflectionMethod('Test', 'methodNullable'), [], fn($type) => $type === 'Test' ? new Test : null),
);
