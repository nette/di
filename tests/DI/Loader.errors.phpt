<?php

/**
 * Test: Nette\DI\Config\Loader: including files
 */

use Nette\DI\Config;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$config = new Config\Loader;
	$config->load('missing.neon');
}, Nette\FileNotFoundException::class, "File 'missing.neon' is missing or is not readable.");

Assert::exception(function () {
	$config = new Config\Loader;
	$config->load(__FILE__);
}, Nette\InvalidArgumentException::class, "Unknown file extension '%a%.phpt'.");

Assert::exception(function () {
	$config = new Config\Loader;
	$config->load('files/neonAdapter.neon', 'unknown');
}, Nette\Utils\AssertionException::class, "Missing section 'unknown' in file '%a%'.");
