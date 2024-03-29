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
			'key' => Expect::string()->deprecated(),
		]);
	}
}


Assert::error(function () {
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	createContainer($compiler, '
	foo:
		key: hello
	');
}, E_USER_DEPRECATED, "The item 'foo\u{a0}›\u{a0}key' is deprecated.");
