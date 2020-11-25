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
	public function methodUnion(\stdClass |self $self)
	{
	}
}

Assert::exception(function () {
	Resolver::autowireArguments(new ReflectionMethod('Test', 'methodUnion'), [], function () {});
}, Nette\InvalidStateException::class, 'The $self in Test::methodUnion() is not expected to have a union type.');
