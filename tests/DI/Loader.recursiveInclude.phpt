<?php

/**
 * Test: Nette\DI\Config\Loader max includes nesting.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$compiler = new DI\Config\Loader();

Assert::exception(function () use ($compiler) {
	$compiler->load('files/loader.recursiveInclude.neon');
}, DI\RecursiveIncludesException::class, 'files/loader.recursiveInclude.neon');
