<?php

/**
 * Test: Nette\DI\Compiler and config.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$compiler = new DI\Compiler;

Assert::same(
	[],
	$compiler->getConfig()
);


$compiler->addConfig([
	'parameters' => [
		'item1' => 1,
	],
]);
$compiler->compile();

Assert::same(
	[
		'parameters' => [
			'item1' => 1,
		],
	],
	$compiler->getConfig()
);
