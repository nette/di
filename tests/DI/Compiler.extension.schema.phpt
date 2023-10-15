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
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::structure([
			'key' => Expect::string(),
		]);
	}
}


testException('Extension with unexpected configuration item', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	createContainer($compiler, '
	foo:
		unknown: 123
	');
}, Nette\DI\InvalidConfigurationException::class, "Unexpected item 'foo\u{a0}›\u{a0}unknown'.");


testException('Extension with invalid type for key', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	createContainer($compiler, '
	foo:
		key: 123
	');
}, Nette\DI\InvalidConfigurationException::class, "The item 'foo\u{a0}›\u{a0}key' expects to be string, 123 given.");


test('Extension with valid string configuration', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	foo:
		key: hello
	');
	Assert::equal((object) ['key' => 'hello'], $foo->getConfig());
});


test('Extension with no key configuration', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	foo:
	');
	Assert::equal((object) ['key' => null], $foo->getConfig());
});


test('Extension without configuration', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	');
	Assert::equal((object) ['key' => null], $foo->getConfig());
});
