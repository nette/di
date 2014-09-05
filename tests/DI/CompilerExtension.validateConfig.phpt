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


Assert::with(new MyExtension, function() {
	$this->validateConfig(array());
	$this->validateConfig(array('a' => TRUE, 'b' => TRUE), array('a' => TRUE));
});

Assert::exception(function() {
	Assert::with(new MyExtension, function() {
		$this->validateConfig(array('a' => TRUE, 'b' => TRUE), array('c' => TRUE));
	});
}, 'Nette\InvalidStateException', 'Unknown configuration option my.c.');

Assert::exception(function() {
	Assert::with(new MyExtension, function() {
		$this->validateConfig(array('a' => TRUE, 'b' => TRUE), array('c' => TRUE, 'd' => TRUE), 'name');
	});
}, 'Nette\InvalidStateException', 'Unknown configuration option name.c, name.d.');

Assert::exception(function() {
	Assert::with(new MyExtension, function() {
		$this->setConfig(array('c' => TRUE, 'd' => TRUE));
		$this->validateConfig(array('a' => TRUE, 'b' => TRUE), NULL, 'name');
	});
}, 'Nette\InvalidStateException', 'Unknown configuration option name.c, name.d.');
