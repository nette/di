<?php

/**
 * Test: Nette\DI\Autowiring::completeArguments()
 */

declare(strict_types=1);

use Nette\DI\Autowiring;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Container
{
	public function getByType($type)
	{
		return $type === 'Test' ? new Test : null;
	}
}

class Test
{
	public function method(self $class, self $self, Undefined $nullable1 = null, int $nullable2 = null)
	{
	}


	public function methodNullable(?self $class, ?self $self, ?Undefined $nullable1, ?int $nullable2)
	{
	}
}

$container = new Container;

Assert::equal(
	[new Test, new Test],
	Autowiring::completeArguments(new ReflectionMethod('Test', 'method'), [], $container)
);

Assert::equal(
	[new Test, new Test],
	Autowiring::completeArguments(new ReflectionMethod('Test', 'methodNullable'), [], $container)
);
