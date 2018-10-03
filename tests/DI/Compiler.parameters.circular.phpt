<?php

/**
 * Test: Nette\DI\Compiler and circular references in parameters.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$loader = new DI\Config\Loader;
	$compiler = new DI\Compiler;
	$compiler->addConfig($loader->load('files/compiler.parameters.circular.neon'))->compile();
}, Nette\InvalidArgumentException::class, 'Circular reference detected for variables: foo, foobar, bar.');
