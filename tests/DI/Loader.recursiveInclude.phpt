<?php

/**
 * Test: Nette\DI\Config\Loader max includes nesting.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$compiler = new DI\Config\Loader;

Assert::exception(
	fn() => $compiler->load('files/loader.recursiveInclude.neon'),
	Nette\InvalidStateException::class,
	"Recursive included file 'files/loader.recursiveInclude.neon'",
);
