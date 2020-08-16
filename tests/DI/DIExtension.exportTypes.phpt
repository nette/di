<?php

/**
 * Test: DIExtension types exporting
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
}


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	di:
		export:
			types: true

	services:
		one:
			factory: stdClass
			autowired: no

		second: stdClass
	');

	Assert::same(['second', 'one'], $container->findByType(stdClass::class));
	Assert::same(['second'], $container->findAutowired(stdClass::class));
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	di:
		export:
			types: false

	services:
		one:
			factory: stdClass
			autowired: no

		second: stdClass
	');

	Assert::same([], $container->findByType(stdClass::class));
	Assert::same([], $container->findAutowired(stdClass::class));
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$compiler->addExportedType(stdClass::class);
	$container = createContainer($compiler, '
	di:
		export:
			types: false

	services:
		one:
			factory: stdClass
			autowired: no

		second: stdClass
	');

	Assert::same(['second', 'one'], $container->findByType(stdClass::class));
	Assert::same(['second'], $container->findAutowired(stdClass::class));
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$compiler->addExportedType(stdClass::class);
	$container = createContainer($compiler, '
	di:
		export:
			types:

	services:
		one:
			factory: Foo
			autowired: no

		second: stdClass
	');

	Assert::same([], $container->findByType(Foo::class));
	Assert::same([], $container->findAutowired(Foo::class));
	Assert::same(['second'], $container->findByType(stdClass::class));
	Assert::same(['second'], $container->findAutowired(stdClass::class));
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$compiler->addExportedType(stdClass::class);
	$container = createContainer($compiler, '
	di:
		export:
			types:
				- Foo

	services:
		one:
			factory: Foo
			autowired: no

		second: stdClass
	');

	Assert::same(['one'], $container->findByType(Foo::class));
	Assert::same([], $container->findAutowired(Foo::class));
	Assert::same(['second'], $container->findByType(stdClass::class));
	Assert::same(['second'], $container->findAutowired(stdClass::class));
});
