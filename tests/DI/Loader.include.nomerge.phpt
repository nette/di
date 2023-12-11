<?php

/**
 * Test: Nette\DI\Config\Loader: including files
 */

declare(strict_types=1);

use Nette\DI\Config;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$config = new Config\Loader;
$data = $config->load('files/loader.includes.neon', merge: false);

Assert::same([
	'files/loader.includes.neon',
	'files/loader.includes.child.neon',
	'files/loader.includes.child.php',
	__DIR__ . DIRECTORY_SEPARATOR . 'files/loader.includes.grandchild.neon',
], $config->getDependencies());

Assert::same([
	[
		'parameters' => ['me' => ['loader.includes.child.neon'], 'scalar' => 3, 'list' => null],
		'services' => ['a' => 'stdClass'],
	],
	['parameters' => ['me' => ['loader.includes.grandchild.neon']]],
	[
		'parameters' => [
			'me' => ['loader.includes.child.php'],
			'scalar' => 4,
			'list' => [5, 6],
			'force' => [5, 6],
		],
	],
	[
		'parameters' => [
			'scalar' => 1,
			'list' => [1, 2],
			'force' => [1, 2, '_prevent_merging' => true],
		],
		'services' => ['a' => ['autowired' => false]],
	],
], $data);
