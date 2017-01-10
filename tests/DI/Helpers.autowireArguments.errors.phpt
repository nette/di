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
	function getByType()
	{}
}

$container = new Container;

Assert::exception(function () use ($container) {
	Helpers::autowireArguments(new ReflectionFunction(function (stdClass $x) {}), [], $container);
}, Nette\DI\ServiceCreationException::class, 'Service of type stdClass needed by {closure}() not found. Did you register it in configuration file?');


Assert::exception(function () use ($container) {
	Helpers::autowireArguments(new ReflectionFunction(function (Foo $x) {}), [], $container);
}, Nette\DI\ServiceCreationException::class, "Class Foo needed by {closure}() not found. Check type hint and 'use' statements.");


Assert::exception(function () use ($container) {
		Helpers::autowireArguments(new ReflectionFunction(function (stdclass $x) {}), [], $container);
	},
	Nette\DI\ServiceCreationException::class,
	PHP_VERSION_ID < 70000
		? 'Service of type stdClass needed by {closure}() not found. Did you register it in configuration file?'
		: 'Service of type stdclass needed by {closure}() not found, did you mean stdClass?'
);


Assert::exception(function () use ($container) {
	Helpers::autowireArguments(new ReflectionFunction(function ($x) {}), [], $container);
}, Nette\DI\ServiceCreationException::class, 'Parameter $x in {closure}() has no class type hint or default value, so its value must be specified.');


Assert::exception(function () use ($container) {
	Helpers::autowireArguments(new ReflectionFunction(function (int $x) {}), [], $container);
}, Nette\DI\ServiceCreationException::class, 'Parameter $x in {closure}() has no class type hint or default value, so its value must be specified.');
