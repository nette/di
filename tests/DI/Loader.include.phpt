<?php

/**
 * Test: Nette\DI\Config\Loader: including files
 */

declare(strict_types=1);

use Nette\DI\Config;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$config = new Config\Loader;
$data = @$config->load('files/loader.includes.neon', 'production'); // @ deprecated

Assert::same([
	'files/loader.includes.neon',
	'files/loader.includes.child.ini',
	'files/loader.includes.child.php',
	__DIR__ . DIRECTORY_SEPARATOR . 'files/loader.includes.grandchild.neon',
], $config->getDependencies());

Assert::same([
	'parameters' => [
		'me' => [
			'loader.includes.child.ini',
			'loader.includes.grandchild.neon',
			'loader.includes.child.php',
		],
		'scalar' => 1,
		'list' => [5, 6, 1, 2],
		'force' => [1, 2],
	],
], $data);
