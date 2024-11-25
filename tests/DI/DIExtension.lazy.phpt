<?php

/**
 * Test: DIExtension lazy services
 * @phpVersion 8.4
 */

declare(strict_types=1);

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
