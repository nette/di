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
});


testException('type not known', function () {
	$container = new Container;
	$container->addService('one', new Service);
	$container->getServiceType('one');
}, Nette\DI\MissingServiceException::class, "Type of service 'one' not known.");


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


testException('getByType', function () {
	$container = new Container;
	$container->addService('one', fn() => new Service);
	$container->getByType(Service::class);
}, Nette\DI\MissingServiceException::class, 'Service of type Service not found. Did you add it to configuration file?');


testException('getByType with typehint', function () {
	$container = new Container;
	$container->addService('one', fn(): Service => new Service);
	$container->getByType(Service::class);
}, Nette\DI\MissingServiceException::class, 'Service of type Service not found. Did you add it to configuration file?');


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
