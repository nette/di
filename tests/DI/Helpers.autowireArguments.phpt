<?php

/**
 * Test: Nette\DI\Config\Helpers::autowireArguments()
 */

declare(strict_types=1);

use Nette\DI\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Container
{
	function getByType($type)
	{
		return $type === 'Test' ? new Test : null;
	}
}

class Test
{
	function method(Test $class, self $self, Undefined $nullable1 = null, int $nullable2 = null)
	{
	}
}

$container = new Container;

Assert::equal(
	[new Test, new Test],
	Helpers::autowireArguments(new ReflectionMethod('Test', 'method'), [], $container)
);
