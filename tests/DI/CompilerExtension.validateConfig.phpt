<?php

/**
 * Test: Nette\DI\CompilerExtension::validateConfig()
 */

declare(strict_types=1);

use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyExtension extends Nette\DI\CompilerExtension
{
	protected $name = 'my';
}


test('', function () {
	$ext = new MyExtension;
	Assert::same([], $ext->validateConfig([]));
	Assert::same(['a' => 2, 'b' => 1], $ext->validateConfig(['a' => 1, 'b' => 1], ['a' => 2]));
});

test('', function () {
	$ext = new MyExtension;
	$ext->setConfig(['a' => 2]);
	Assert::same(['a' => 2, 'b' => 1], $ext->validateConfig(['a' => 1, 'b' => 1]));
	Assert::same(['a' => 2, 'b' => 1], $ext->getConfig());
});

test('', function () {
	$ext = new MyExtension;
	$ext->setConfig(['a' => 2]);
	Assert::same(['a' => 3, 'b' => 1], $ext->validateConfig(['a' => 1, 'b' => 1], ['a' => 3]));
	Assert::same(['a' => 2], $ext->getConfig());
});

test('', function () {
	$ext = new MyExtension;
	$ext->setConfig(['a' => 2]);
	Assert::same(['a' => 1, 'b' => 1], $ext->validateConfig(['a' => 1, 'b' => 1], null));
	Assert::same(['a' => 2], $ext->getConfig());
});

Assert::exception(function () {
	$ext = new MyExtension;
	$ext->validateConfig(['a' => 1, 'b' => 1], ['c' => 1]);
}, Nette\DI\InvalidConfigurationException::class, "Unknown configuration option 'my\u{a0}›\u{a0}c', did you mean 'my\u{a0}›\u{a0}a'?");

Assert::exception(function () {
	$ext = new MyExtension;
	$ext->validateConfig(['a' => 1, 'b' => 1], ['ccc' => 1, 'ddd' => 2]);
}, Nette\DI\InvalidConfigurationException::class, "Unknown configuration option 'my\u{a0}›\u{a0}ccc', 'my\u{a0}›\u{a0}ddd'.");

Assert::exception(function () {
	$ext = new MyExtension;
	$ext->validateConfig(['a' => 1, 'b' => 1], ['c' => 1, 'd' => 1], 'name.x');
}, Nette\DI\InvalidConfigurationException::class, "Unknown configuration option 'name\u{a0}›\u{a0}x\u{a0}›\u{a0}c', did you mean 'name\u{a0}›\u{a0}x\u{a0}›\u{a0}a'?");

Assert::exception(function () {
	$ext = new MyExtension;
	$ext->setConfig(['c' => 1, 'd' => 1]);
	$ext->validateConfig(['a' => 1, 'b' => 1]);
}, Nette\DI\InvalidConfigurationException::class, "Unknown configuration option 'my\u{a0}›\u{a0}c', did you mean 'my\u{a0}›\u{a0}a'?");
