<?php

/**
 * Test: Nette\DI\Config\Loader: including files
 */

declare(strict_types=1);

use Nette\DI\Config;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


testException('', function () {
	$config = new Config\Loader;
	$data = $config->load('files/loader.includes.params.neon');
}, Nette\InvalidArgumentException::class, "Missing parameter 'name'.");


test('', function () {
	$config = new Config\Loader;
	$config->setParameters(['name' => 'loader.includes.params.child']);
	$data = $config->load('files/loader.includes.params.neon');

	Assert::same([
		'files/loader.includes.params.neon',
		'files/loader.includes.params.child.neon',
	], $config->getDependencies());

	Assert::same([
		'parameters' => [
			'foo' => 'bar',
			'name' => 'ignored',
		],
	], $data);
});
