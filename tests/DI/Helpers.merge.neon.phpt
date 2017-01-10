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
	file_put_contents(TEMP_DIR . '/left.neon', $left);
	file_put_contents(TEMP_DIR . '/right.neon', $right);

	$config = new Config\Loader;
	return Config\Helpers::merge($config->load(TEMP_DIR . '/left.neon'), $config->load(TEMP_DIR . '/right.neon'));
}


// replace
Assert::same(['item' => []], merge('item!:', 'item:'));

Assert::same(['item' => []], merge('item!:', 'item: 123'));

Assert::same(['item' => []], merge('item!: []', 'item: []'));

Assert::exception(function () {
	merge('item!: 231', 'item:');
}, Nette\InvalidStateException::class);

Assert::exception(function () {
	merge('item!: 231', 'item: 231');
}, Nette\InvalidStateException::class);


// inherit
Assert::same([
	'parent' => 1,
	'child' => [Config\Helpers::EXTENDS_KEY => 'parent']
], @merge('child < parent:', 'parent: 1')); // @ deprecated
