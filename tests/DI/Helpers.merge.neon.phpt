<?php

/**
 * Test: Nette\DI\Config\Helpers::merge() with NeonAdapter
 */

declare(strict_types=1);

use Nette\DI\Config;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$obj = new stdClass;
$arr1 = ['a' => 'b', 'x'];
$arr2 = ['c' => 'd', 'y'];


function merge($left, $right)
{
	file_put_contents(getTempDir() . '/left.neon', $left);
	file_put_contents(getTempDir() . '/right.neon', $right);

	$config = new Config\Loader;
	return Config\Helpers::merge($config->load(getTempDir() . '/left.neon'), $config->load(getTempDir() . '/right.neon'));
}


// replace
Assert::same(['item' => []], merge('item!:', 'item:'));

Assert::same(['item' => []], merge('item!:', 'item: 123'));

Assert::same(['item' => []], merge('item!: []', 'item: []'));

Assert::exception(
	fn() => merge('item!: 231', 'item:'),
	Nette\DI\InvalidConfigurationException::class,
);

Assert::exception(
	fn() => merge('item!: 231', 'item: 231'),
	Nette\DI\InvalidConfigurationException::class,
);
