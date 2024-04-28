<?php

/**
 * Test: Nette\DI\Container dynamic usage.
 */

declare(strict_types=1);

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
}


test('basic', function () {
	$container = new Container;
	$one = new Service;
	$two = new Service;
	$container->addService('one', $one);
	$container->addService('two', $two);

	Assert::true($container->hasService('one'));
	Assert::true($container->isCreated('one'));
	Assert::true($container->hasService('two'));
	Assert::false($container->hasService('undefined'));

	Assert::same($one, $container->getService('one'));
	Assert::same($two, $container->getService('two'));

	Assert::same(Service::class, $container->getServiceType('one'));
	Assert::same(Service::class, $container->getServiceType('two'));
});


test('closure', function () {
	$container = new Container;
	$container->addService('four', fn() => new Service);

	Assert::true($container->hasService('four'));
	Assert::false($container->isCreated('four'));
	Assert::true($container->getService('four') instanceof Service);
	Assert::true($container->isCreated('four'));
	Assert::same($container->getService('four'), $container->getService('four')); // shared

	Assert::same('', $container->getServiceType('four'));
});


test('closure with typehint', function () {
	$container = new Container;
	$container->addService('five', fn(): Service => new Service);

	Assert::same(Service::class, $container->getServiceType('five'));
});


testException('bad closure', function () {
	$container = new Container;
	$container->addService('six', function () {});
	$container->getService('six');
}, Nette\UnexpectedValueException::class, "Unable to create service 'six', value returned by closure is not object.");


testException('union type', function () {
	$container = new Container;
	$container->addService('six', function (): stdClass|Closure {});
	$container->getService('six');
}, Nette\InvalidStateException::class, "Return type of closure is expected to not be nullable/built-in/complex, 'stdClass|Closure' given.");
