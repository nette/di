<?php

/**
 * Test: Nette\DI\Container static & dynamic usage.
 */

declare(strict_types=1);

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyContainer extends Container
{
	protected function createServiceOne()
	{
		return new stdClass;
	}


	protected function createServiceTypehint(): stdClass
	{
		return new stdClass;
	}
}


test('basic', function () {
	$container = new MyContainer;

	Assert::true($container->hasService('one'));

	$container->addService('one', new stdClass);

	Assert::true($container->hasService('one'));
	Assert::same('', $container->getServiceType('one'));

	Assert::type(stdClass::class, $container->getService('one'));
	Assert::same($container->getService('one'), $container->getService('one')); // shared
});


test('closure', function () {
	$container = new MyContainer;

	$container->addService('one', fn() => new stdClass);

	Assert::true($container->hasService('one'));
	Assert::same('', $container->getServiceType('one'));
	Assert::type(stdClass::class, $container->getService('one'));
	Assert::same($container->getService('one'), $container->getService('one')); // shared
});


test('closure & typehint', function () {
	$container = new MyContainer;

	$container->addService('one', fn(): stdClass => new stdClass);

	Assert::same(stdClass::class, $container->getServiceType('one'));
	Assert::true($container->hasService('one'));
	Assert::type(stdClass::class, $container->getService('one'));
});


test('closure & matching typehint', function () {
	$container = new MyContainer;

	class MyClass extends stdClass
	{
	}

	$container->addService('typehint', fn(): MyClass => new MyClass);

	Assert::same(MyClass::class, $container->getServiceType('typehint'));
	Assert::true($container->hasService('typehint'));
	Assert::type(MyClass::class, $container->getService('typehint'));
});


testException('closure & wrong typehint', function () {
	$container = new MyContainer;
	$container->addService('typehint', fn() => new DateTime);
}, Nette\InvalidArgumentException::class, "Service 'typehint' must be instance of stdClass, add typehint to closure.");


testException('closure & wrong typehint', function () {
	$container = new MyContainer;
	$container->addService('typehint', fn(): DateTime => new DateTime);
}, Nette\InvalidArgumentException::class, "Service 'typehint' must be instance of stdClass, DateTime given.");
