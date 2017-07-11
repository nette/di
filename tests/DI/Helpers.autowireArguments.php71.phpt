<?php

/**
 * Test: Nette\DI\Config\Helpers::autowireArguments()
 * @phpversion 7.1
 */

declare(strict_types=1);

use Nette\DI\Helpers;
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
	public function method(Test $class, self $self, Undefined $nullable1 = null, int $nullable2 = null)
	{
	}


	public function methodNullable(?Test $class, ?self $self, ?Undefined $nullable1, ?int $nullable2)
	{
	}
}

$container = new Container;

Assert::equal(
	[new Test, new Test],
	Helpers::autowireArguments(new ReflectionMethod('Test', 'method'), [], $container)
);

Assert::equal(
	[new Test, new Test],
	Helpers::autowireArguments(new ReflectionMethod('Test', 'methodNullable'), [], $container)
);
