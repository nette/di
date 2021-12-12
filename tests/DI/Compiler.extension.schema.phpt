<?php

/**
 * Test: Nette\DI\CompilerExtension and schema validation
 */

declare(strict_types=1);

use Nette\Schema\Expect;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class FooExtension extends Nette\DI\CompilerExtension
{
	public $loadedConfig;


	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::structure([
			'key' => Expect::string(),
		]);
	}


	public function loadConfiguration()
	{
		$this->loadedConfig = $this->config;
	}
}


Assert::exception(function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	foo:
		unknown: 123
	');
}, Nette\DI\InvalidConfigurationException::class, "Unexpected item 'foo\u{a0}›\u{a0}unknown'.");


Assert::exception(function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	foo:
		key: 123
	');
}, Nette\DI\InvalidConfigurationException::class, "The item 'foo\u{a0}›\u{a0}key' expects to be string, 123 given.");


test('', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	foo:
		key: hello
	');
	Assert::equal((object) ['key' => 'hello'], $foo->loadedConfig);
});


test('', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	foo:
	');
	Assert::equal((object) ['key' => null], $foo->loadedConfig);
});


test('', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	');
	Assert::equal((object) ['key' => null], $foo->loadedConfig);
});
