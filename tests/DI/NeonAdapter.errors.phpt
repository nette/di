<?php

/**
 * Test: Nette\DI\Config\Adapters\NeonAdapter errors.
 */

use Nette\DI\Config;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$config = new Config\Loader;
	@$config->load('files/neonAdapter.scalar.neon'); // @ deprecated
}, Nette\InvalidStateException::class, "Duplicated key 'scalar'.");
