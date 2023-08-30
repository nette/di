<?php

/**
 * Test: DIExtension parameters exporting
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	parameters:
		key: val

	di:
		export:
			parameters: true
	');

	Assert::same(['key' => 'val'], $container->parameters);
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	parameters:
		key: val

	di:
		export:
			parameters: false
	');

	Assert::same([], $container->parameters);
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	parameters:
		key: %dynamic%

	di:
		export:
			parameters: true
	', ['dynamic' => 123]);

	Assert::same(['dynamic' => 123, 'key' => 123], $container->parameters);
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	parameters:
		key: %dynamic%

	di:
		export:
			parameters: false
	', ['dynamic' => 123]);

	Assert::same(['dynamic' => 123], $container->parameters);
});
