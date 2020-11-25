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
	public function methodUnion(int|self $self, self|Undefined $self2, Undefined1|Undefined2|null $nullable)
	{
	}
}

Assert::equal(
	[new Test, new Test, null],
	Resolver::autowireArguments(new ReflectionMethod('Test', 'methodUnion'), [], function ($type) {
		return $type === 'Test' ? new Test : null;
	})
);
