<?php

/**
 * Test: Nette\DI\CompilerExtension and getConfig
 */

use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class FooExtension extends Nette\DI\CompilerExtension
{
	public $barConfig;


	public function loadConfiguration()
	{
		$exts = $this->compiler->getExtensions();
		$this->barConfig = $exts['bar']->getConfig();
	}
}


class BarExtension extends Nette\DI\CompilerExtension
{
}


$compiler = new Nette\DI\Compiler;
$compiler->addExtension('foo', $foo = new FooExtension);
$compiler->addExtension('bar', new BarExtension);
createContainer($compiler, '
bar:
	lorem: ipsum
');

Assert::same(['lorem' => 'ipsum'], $foo->barConfig);
