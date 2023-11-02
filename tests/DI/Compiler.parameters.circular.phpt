<?php

/**
 * Test: Nette\DI\Compiler and circular references in parameters.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$config = '
parameters:
	bar: %foo%
	foo: %foobar%
	foobar: %bar%
';
	$loader = new DI\Config\Loader;
	$compiler = new DI\Compiler;
	$compiler->addConfig($loader->load(Tester\FileMock::create($config, 'neon')))->compile();
}, Nette\InvalidArgumentException::class, 'Circular reference detected for parameters: %foo%, %foobar%, %bar%');


Assert::exception(function () {
	$config = '
parameters:
	outer:
		inner: %outer.inner%
';
	$loader = new DI\Config\Loader;
	$compiler = new DI\Compiler;
	$compiler->addConfig($loader->load(Tester\FileMock::create($config, 'neon')))->compile();
}, Nette\InvalidArgumentException::class, 'Circular reference detected for parameters: %outer.inner%');
