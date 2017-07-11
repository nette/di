<?php

/**
 * Test: Nette\DI\Config\Helpers::autowireArguments()
 * @phpversion 7.1
 */

use Nette\DI\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Container
{
	function getByType($type)
	{
		return $type === 'Test' ? new Test : NULL;
	}
}

class Test
{
	function method(Test $class, self $self, Undefined $nullable1 = NULL, int $nullable2 = NULL)
	{
	}


	function methodNullable(?Test $class, ?self $self, ?Undefined $nullable1, ?int $nullable2)
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
