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


class Test implements Foo
{
	public function methodIntersection(Foo&Test $self)
	{
	}
}


Assert::exception(function () {
	Resolver::autowireArguments(new ReflectionMethod(Test::class, 'methodIntersection'), [], function () {});
}, Nette\InvalidStateException::class, 'Parameter $self in Test::methodIntersection() has intersection type, so its value must be specified.');
