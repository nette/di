<?php

/**
 * Test: Nette\DI\Compiler alias for nette config
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestExtension extends Nette\DI\CompilerExtension
{
}


test(function() {
	$compiler = new DI\Compiler;
	$compiler->addExtension('nette', new TestExtension);
	$compiler->addExtension('test', $extension = new TestExtension);
	$container = createContainer($compiler, '
	nette:
		test:
			item: 10
	');

	Assert::same(array('item' => 10), $extension->getConfig());
});


test(function() {
	$compiler = new DI\Compiler;
	$compiler->addExtension('nette', new TestExtension);
	$compiler->addExtension('test', $extension = new TestExtension);
	$container = createContainer($compiler, '
	nette:
		test: 10

	test:
		item: 20
	');

	Assert::same(array('item' => 20), $extension->getConfig());
});


Assert::exception(function() {
	$compiler = new DI\Compiler;
	$compiler->addExtension('nette', new TestExtension);
	$compiler->addExtension('test', $extension = new TestExtension);
	$container = createContainer($compiler, '
	nette:
		test:
			item: 10

	test:
		item: 20
	');
}, 'Nette\DeprecatedException', "Configuration section 'nette.test' is deprecated, move it to section 'test'.");
