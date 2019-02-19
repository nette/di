<?php

/**
 * Test: Nette\DI\Compiler: including files
 */

declare(strict_types=1);

use Nette\DI\Compiler;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$compiler = new Compiler;
$compiler->loadConfig('files/loader.includes.neon');

Assert::same([
	'files/loader.includes.neon',
	'files/loader.includes.child.neon',
	'files/loader.includes.child.php',
	__DIR__ . DIRECTORY_SEPARATOR . 'files/loader.includes.grandchild.neon',
], array_keys($compiler->exportDependencies()[1]));


Assert::same([
	'services' => ['a' => ['factory' => 'stdClass', 'autowired' => false]],
	'parameters' => [
		'me' => [
			'loader.includes.child.neon',
			'loader.includes.grandchild.neon',
			'loader.includes.child.php',
		],
		'scalar' => 1,
		'list' => [5, 6, 1, 2],
		'force' => [1, 2],
	],
], $compiler->getConfig());
