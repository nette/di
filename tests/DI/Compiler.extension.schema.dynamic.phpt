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
			'key' => Expect::string()->dynamic(),
		]);
	}
}


Assert::exception(function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	createContainer($compiler, '
	foo:
		key: 123
	');
}, Nette\DI\InvalidConfigurationException::class, "The item 'foo\u{a0}›\u{a0}key' expects to be %a?%string, 123 given.");


test('valid type', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	foo:
		key: hello
	');
	Assert::equal((object) ['key' => 'hello'], $foo->getConfig());
});


test('Statement', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	foo:
		key: ::trim("x")
	');
	Assert::type(Nette\DI\Definitions\Statement::class, $foo->getConfig()->key);
});


test('Statement via parameter', function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', $foo = new FooExtension);
	createContainer($compiler, '
	parameters:
		dynamic: ::trim("x")

	foo:
		key: %dynamic%
	');
	Assert::type(Nette\DI\Definitions\Statement::class, $foo->getConfig()->key);
});
