<?php

/**
 * Test: Nette\DI\Config\Loader: including files
 */

declare(strict_types=1);

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
