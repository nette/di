<?php declare(strict_types=1);

/**
 * Test: DIExtension lazy services
 */

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Service
{
	private $id;


	public function __construct()
	{
	}
}


class Plain
{
}


function isLazy(object $obj): bool
{
	return new ReflectionObject($obj)->isUninitializedLazyObject($obj);
}


test('Eager is default', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	services:
		internal: stdClass
		trivial: stdClass
		default: Service(10)
		eager:
			create: Service(10)
			lazy: false
		lazy:
			create: Service(10)
			lazy: true
	');

	Assert::false(isLazy($container->getByName('internal')));
	Assert::false(isLazy($container->getByName('trivial')));
	Assert::false(isLazy($container->getByName('default')));
	Assert::false(isLazy($container->getByName('eager')));
	Assert::true(isLazy($container->getByName('lazy')));
});


test('A constructor without arguments is deferred, a class without constructor is not', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	services:
		constructor:
			create: Service
			lazy: true
		plain:
			create: Plain
			lazy: true
	');

	Assert::true(isLazy($container->getByName('constructor')));
	Assert::false(isLazy($container->getByName('plain')));
});


test('Lazy is default', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	di:
		lazy: true

	services:
		internal: stdClass
		trivial: stdClass
		default: Service(10)
		eager:
			create: Service(10)
			lazy: false
		lazy:
			create: Service(10)
			lazy: true
	');

	Assert::false(isLazy($container->getByName('internal')));
	Assert::false(isLazy($container->getByName('trivial')));
	Assert::true(isLazy($container->getByName('default')));
	Assert::false(isLazy($container->getByName('eager')));
	Assert::true(isLazy($container->getByName('lazy')));
});
