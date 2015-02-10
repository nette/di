<?php

/**
 * Test: Nette\DI\Compiler and addExtension on loadConfiguration stage.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class FooExtension extends DI\CompilerExtension
{
	public function loadConfiguration()
	{
		$this->compiler->addExtension('bar', new self);
	}
}


Assert::exception(function() {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$container = createContainer($compiler);
}, 'Nette\DeprecatedException', "Extensions 'bar' were added while container was being compiled.");
