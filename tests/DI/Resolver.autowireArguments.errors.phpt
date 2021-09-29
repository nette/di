<?php

/**
 * Test: Nette\DI\Resolver::autowireArguments()
 */

declare(strict_types=1);

use Nette\DI\Resolver;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	Resolver::autowireArguments(new ReflectionFunction(function (stdClass $x) {}), [], function () {});
}, Nette\DI\ServiceCreationException::class, 'Service of type stdClass required by $x in {closure}() not found. Did you add it to configuration file?');


Assert::exception(function () {
	Resolver::autowireArguments(new ReflectionFunction(function (Foo $x) {}), [], function () {});
}, Nette\DI\ServiceCreationException::class, "Class 'Foo' required by \$x in {closure}() not found. Check the parameter type and 'use' statements.");


Assert::exception(function () {
	Resolver::autowireArguments(new ReflectionFunction(function ($x) {}), [], function () {});
}, Nette\DI\ServiceCreationException::class, 'Parameter $x in {closure}() has no class type or default value, so its value must be specified.');


Assert::exception(function () {
	Resolver::autowireArguments(new ReflectionFunction(function (int $x) {}), [], function () {});
}, Nette\DI\ServiceCreationException::class, 'Parameter $x in {closure}() has no class type or default value, so its value must be specified.');


Assert::exception(function () {
	Resolver::autowireArguments(new ReflectionFunction(function (int $x) {}), [10, 'x' => 10], function () {});
}, Nette\DI\ServiceCreationException::class, 'Named parameter $x used at the same time as a positional in {closure}%a?%.');


Assert::exception(function () {
	Resolver::autowireArguments(new ReflectionFunction(function (...$args) {}), ['args' => []], function () {});
}, Nette\DI\ServiceCreationException::class, 'Unable to pass specified arguments to {closure}%a?%.');
