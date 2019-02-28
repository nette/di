<?php

/**
 * Test: Nette\DI\Resolver::autowireArguments()
 */

declare(strict_types=1);

use Nette\DI\Resolver;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Container
{
	public function getByType()
	{
	}
}

$container = new Container;

Assert::exception(function () use ($container) {
	Resolver::autowireArguments(new ReflectionFunction(function (stdClass $x) {}), [], $container);
}, Nette\DI\ServiceCreationException::class, 'Service of type stdClass needed by $x in {closure}() not found. Did you register it in configuration file?');


Assert::exception(function () use ($container) {
	Resolver::autowireArguments(new ReflectionFunction(function (Foo $x) {}), [], $container);
}, Nette\DI\ServiceCreationException::class, "Class Foo needed by \$x in {closure}() not found. Check type hint and 'use' statements.");


Assert::exception(function () use ($container) {
	Resolver::autowireArguments(new ReflectionFunction(function ($x) {}), [], $container);
}, Nette\DI\ServiceCreationException::class, 'Parameter $x in {closure}() has no class type hint or default value, so its value must be specified.');


Assert::exception(function () use ($container) {
	Resolver::autowireArguments(new ReflectionFunction(function (int $x) {}), [], $container);
}, Nette\DI\ServiceCreationException::class, 'Parameter $x in {closure}() has no class type hint or default value, so its value must be specified.');
