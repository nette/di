<?php

/**
 * Test: Nette\DI\CompilerExtension::validateConfig()
 * @phpversion 5.4
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyExtension extends Nette\DI\CompilerExtension
{
	protected $name = 'my';
}


test(function() {
	$ext = new MyExtension;
	Assert::same(array(), $ext->validateConfig(array()));
	Assert::same(array('a' => 2, 'b' => 1), $ext->validateConfig(array('a' => 1, 'b' => 1), array('a' => 2)));
});

test(function() {
	$ext = new MyExtension;
	$ext->setConfig(array('a' => 2));
	Assert::same(array('a' => 2, 'b' => 1), $ext->validateConfig(array('a' => 1, 'b' => 1)));
	Assert::same(array('a' => 2, 'b' => 1), $ext->getConfig());
});

test(function() {
	$ext = new MyExtension;
	$ext->setConfig(array('a' => 2));
	Assert::same(array('a' => 3, 'b' => 1), $ext->validateConfig(array('a' => 1, 'b' => 1), array('a' => 3)));
	Assert::same(array('a' => 2), $ext->getConfig());
});

test(function() {
	$ext = new MyExtension;
	$ext->setConfig(array('a' => 2));
	Assert::same(array('a' => 1, 'b' => 1), $ext->validateConfig(array('a' => 1, 'b' => 1), NULL));
	Assert::same(array('a' => 2), $ext->getConfig());
});

Assert::exception(function() {
	$ext = new MyExtension;
	$ext->validateConfig(array('a' => 1, 'b' => 1), array('c' => 1));
}, 'Nette\InvalidStateException', 'Unknown configuration option my.c.');

Assert::exception(function() {
	$ext = new MyExtension;
	$ext->validateConfig(array('a' => 1, 'b' => 1), array('c' => 1, 'd' => 1), 'name');
}, 'Nette\InvalidStateException', 'Unknown configuration option name.c, name.d.');

Assert::exception(function() {
	$ext = new MyExtension;
	$ext->setConfig(array('c' => 1, 'd' => 1));
	$ext->validateConfig(array('a' => 1, 'b' => 1));
}, 'Nette\InvalidStateException', 'Unknown configuration option my.c, my.d.');
