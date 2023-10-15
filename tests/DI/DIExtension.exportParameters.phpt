<?php

/**
 * Test: DIExtension parameters exporting
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('Parameters are exported when setting is true', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	parameters:
		key: val

	di:
		export:
			parameters: true
	');

	Assert::same(['key' => 'val'], $container->getParameters());
});


test('Parameters are not exported when setting is false', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('di', new DIExtension);
	$container = createContainer($compiler, '
	parameters:
		key: val

	di:
		export:
			parameters: false
	');

	Assert::same([], $container->getParameters());
});


test('Dynamic parameters are correctly exported when export setting is true', function () {
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

	Assert::same(['dynamic' => 123, 'key' => null], $container->getParameters());
});


test('Static parameters are not exported when setting is false', function () {
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

	Assert::same(['dynamic' => 123], $container->getParameters());
});
