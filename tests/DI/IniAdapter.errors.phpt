<?php

/**
 * Test: Nette\DI\Config\Adapters\IniAdapter errors.
 */

use Nette\DI\Config;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$config = new Config\Loader;
	$config->load('files/iniAdapter.scalar1.ini');
}, Nette\InvalidStateException::class, 'Invalid section [scalar.set].');


Assert::exception(function () {
	$config = new Config\Loader;
	$config->load('files/iniAdapter.scalar2.ini');
}, Nette\InvalidStateException::class, "Invalid key 'date.timezone' in section [set].");


Assert::exception(function () {
	$config = new Config\Loader;
	$config->load('files/iniAdapter.malformed.ini');
}, Nette\InvalidStateException::class, "%a?%syntax error, unexpected \$end, expecting ']' in %a% on line 1");
