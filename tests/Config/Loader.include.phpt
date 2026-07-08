<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Config\Loader: including files
 */

use Nette\DI\Config;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$config = new Config\Loader;
$data = $config->load('files/loader.includes.neon');

Assert::same([
	'files/loader.includes.neon',
	'files/loader.includes.child.neon',
	'files/loader.includes.child.php',
	__DIR__ . DIRECTORY_SEPARATOR . 'files/loader.includes.grandchild.neon',
], $config->getDependencies());

Assert::same([
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
	'services' => ['a' => ['autowired' => false]],
], $data);
