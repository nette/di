<?php

/**
 * Test: Nette\DI\Compiler and addExtension on loadConfiguration stage.
 */

declare(strict_types=1);

use Nette\DI;


require __DIR__ . '/../bootstrap.php';


class FooExtension extends DI\CompilerExtension
{
	public function loadConfiguration()
	{
		$this->compiler->addExtension('bar', new self);
	}
}


testException('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$container = createContainer($compiler);
}, Nette\DeprecatedException::class, "Extensions 'bar' were added while container was being compiled.");


testException('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->addExtension('foo', new FooExtension);
}, Nette\InvalidArgumentException::class, "Name 'foo' is already used or reserved.");


testException('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->addExtension('Foo', new FooExtension);
}, Nette\InvalidArgumentException::class, "Name of extension 'Foo' has the same name as 'foo' in a case-insensitive manner.");
