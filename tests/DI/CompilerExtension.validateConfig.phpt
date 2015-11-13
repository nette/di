<?php

/**
 * Test: Nette\DI\CompilerExtension::validateConfig()
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyExtension extends Nette\DI\CompilerExtension
{
	protected $name = 'my';
}


test(function () {
	$ext = new MyExtension;
	Assert::same([], $ext->validateConfig([]));
	Assert::same(['a' => 2, 'b' => 1], $ext->validateConfig(['a' => 1, 'b' => 1], ['a' => 2]));
});

test(function () {
	$ext = new MyExtension;
	$ext->setConfig(['a' => 2]);
	Assert::same(['a' => 2, 'b' => 1], $ext->validateConfig(['a' => 1, 'b' => 1]));
	Assert::same(['a' => 2, 'b' => 1], $ext->getConfig());
});

test(function () {
	$ext = new MyExtension;
	$ext->setConfig(['a' => 2]);
	Assert::same(['a' => 3, 'b' => 1], $ext->validateConfig(['a' => 1, 'b' => 1], ['a' => 3]));
	Assert::same(['a' => 2], $ext->getConfig());
});

test(function () {
	$ext = new MyExtension;
	$ext->setConfig(['a' => 2]);
	Assert::same(['a' => 1, 'b' => 1], $ext->validateConfig(['a' => 1, 'b' => 1], NULL));
	Assert::same(['a' => 2], $ext->getConfig());
});

Assert::exception(function () {
	$ext = new MyExtension;
	$ext->validateConfig(['a' => 1, 'b' => 1], ['c' => 1]);
}, Nette\InvalidStateException::class, 'Unknown configuration option my.c, did you mean my.a?');

Assert::exception(function () {
	$ext = new MyExtension;
	$ext->validateConfig(['a' => 1, 'b' => 1], ['c' => 1, 'd' => 1], 'name');
}, Nette\InvalidStateException::class, 'Unknown configuration option name.c, did you mean name.a?');

Assert::exception(function () {
	$ext = new MyExtension;
	$ext->setConfig(['c' => 1, 'd' => 1]);
	$ext->validateConfig(['a' => 1, 'b' => 1]);
}, Nette\InvalidStateException::class, 'Unknown configuration option my.c, did you mean my.a?');
