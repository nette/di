<?php

/**
 * Test: DIExtension tags exporting
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('Tag are exported when setting is true', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	di:
		export:
			tags: true

	services:
		-
			create: stdClass
			tags:
				first: a
				second: b
	');

	Assert::same(['01' => 'a'], $container->findByTag('first'));
	Assert::same(['01' => 'b'], $container->findByTag('second'));
});


test('Tags are not exported when setting is false', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$compiler->addExportedTag('first');
	$container = createContainer($compiler, '
	di:
		export:
			tags: no

	services:
		-
			create: stdClass
			tags:
				first: a
				second: b
	');

	Assert::same([], $container->findByTag('first'));
	Assert::same([], $container->findByTag('second'));
});


test('Default tag export behavior without explicit setting', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$compiler->addExportedTag('first');
	$container = createContainer($compiler, '
	di:
		export:
			tags:

	services:
		-
			create: stdClass
			tags:
				first: a
				second: b
	');

	Assert::same(['01' => 'a'], $container->findByTag('first'));
	Assert::same([], $container->findByTag('second'));
});


test('Specific tag export when listed', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$compiler->addExportedTag('second');
	$container = createContainer($compiler, '
	di:
		export:
			tags:
				- first

	services:
		-
			create: stdClass
			tags:
				first: a
				second: b
	');

	Assert::same(['01' => 'a'], $container->findByTag('first'));
	Assert::same(['01' => 'b'], $container->findByTag('second'));
});
