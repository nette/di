<?php

/**
 * Test: Nette\DI\Compiler and circular references in parameters.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$loader = new DI\Config\Loader;
	$compiler = new DI\Compiler;
	$compiler->addConfig($loader->load('files/compiler.parameters.circular.ini'))->compile();
}, Nette\InvalidArgumentException::class, 'Circular reference detected for variables: foo, foobar, bar.');
