<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
	public const Name = 'hello';

	public $arg;


	public static function method($name)
	{
		return __METHOD__ . ' ' . $name;
	}


	public function __construct($arg)
	{
		$this->arg = $arg;
	}
}


test('Statement as parameter', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: ::trim(" a ")

	services:
		one: Service(%bar%)
	');

	Assert::same(['bar' => 'a'], $container->getParameters());
	Assert::same('a', $container->getParameter('bar'));
	Assert::same('a', $container->getService('one')->arg);
});


test('Statement within string expansion', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: ::trim(" a ")
		expand: hello%bar%

	services:
		one: Service(%expand%)
	');

	Assert::same(
		['bar' => 'a', 'expand' => 'helloa'],
		$container->getParameters()
	);
	Assert::same('helloa', $container->getService('one')->arg);
});


test('NOT class constant as parameter', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: Service::Name

	services:
		one: Service(%bar%)
	');

	Assert::same(['bar' => 'Service::Name'], $container->getParameters()); // not resolved
	Assert::same('hello', $container->getService('one')->arg);
});


test('Class method and constant resolution', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: Service::method(Service::Name)

	services:
		one: Service(%bar%)
	');

	Assert::same(['bar' => 'Service::method hello'], $container->getParameters());
	Assert::same('Service::method hello', $container->getService('one')->arg);
});


test('Parameter NOT referencing a service', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: @two

	services:
		one: Service(%bar%)
		two: Service(two)
	');

	// intentionally not resolved, it is not possible to distinguish a string from a reference
	Assert::same(['bar' => '@two'], $container->getParameters());
	Assert::same($container->getService('two'), $container->getService('one')->arg);
});


test('Parameter as an instantiated class', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: Service(@two)

	services:
		one: Service(%bar%)
		two: Service(two)
	');

	Assert::equal(
		['bar' => new Service($container->getService('two'))],
		$container->getParameters()
	);
	Assert::same($container->getService('two'), $container->getService('one')->arg->arg);
});


test('Parameter as array of services', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: typed(Service)

	services:
		one: Service(%bar%)
		two: Service(two)
	');

	Assert::same(
		['bar' => [$container->getService('one'), $container->getService('two')]],
		$container->getParameters()
	);
	Assert::same([$container->getService('two')], $container->getService('one')->arg);
});


test('Invalid statement as parameter', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: unknown()
	');

	Assert::same(['bar' => 'unable to resolve'], $container->getParameters());
});


test('Invalid statement as parameter', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: Service::unknown()
	');

	Assert::same(['bar' => 'unable to resolve'], $container->getParameters());
});
