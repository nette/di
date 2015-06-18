<?php

/**
 * Test: Nette\DI\Config\Loader: including files
 */

use Nette\DI\Config;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$config = new Config\Loader;
$data = $config->load('files/loader.includes.neon', 'production');

Assert::same([
	realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'loader.includes.neon'),
	realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'loader.includes.child.ini'),
	realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'loader.includes.child.php'),
], $config->getDependencies());

Assert::same([
	'parameters' => [
		'me' => [
			'loader.includes.child.ini',
			'loader.includes.child.php',
		],
		'scalar' => 1,
		'list' => [5, 6, 1, 2],
		'force' => [1, 2],
	],
], $data);
