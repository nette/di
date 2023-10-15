<?php

/**
 * Test: Nette\DI\CompilerExtension and schema validation
 */

declare(strict_types=1);

use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class FooExtension extends Nette\DI\CompilerExtension
{
	public function __construct()
	{
		$this->config = new class {
			/** @var ?string */
			public $key;
		};
	}
}


testException('Unexpected configuration item', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	createContainer($compiler, '
	foo:
		unknown: 123
	');
}, Nette\DI\InvalidConfigurationException::class, "Unexpected item 'foo\u{a0}›\u{a0}unknown'.");


testException('Mismatched data type for configuration key', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	createContainer($compiler, '
	foo:
		key: 123
	');
}, Nette\DI\InvalidConfigurationException::class, "The item 'foo\u{a0}›\u{a0}key' expects to be ?string, 123 given.");


test('Successful configuration with a provided key', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	foo:
		key: hello
	');
	Assert::type('object', $foo->getConfig());
	Assert::equal(['key' => 'hello'], (array) $foo->getConfig());
});


test('Successful configuration without any specific key', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	foo:
	');
	Assert::type('object', $foo->getConfig());
	Assert::equal(['key' => null], (array) $foo->getConfig());
});


test('Successful configuration with default values', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	');
	Assert::type('object', $foo->getConfig());
	Assert::equal(['key' => null], (array) $foo->getConfig());
});
