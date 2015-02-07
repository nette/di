<?php

/**
 * Test: Nette\DI\CompilerExtension and getConfig
 *
 */

use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class FooExtension extends \Nette\DI\CompilerExtension
{

	public $barConfig;


	public function loadConfiguration()
	{
		$this->compiler->addExtension('bar', $ext = new BarExtension());
		$this->barConfig = $ext->getConfig();
	}

}


class BarExtension extends \Nette\DI\CompilerExtension
{

}


$compiler = new \Nette\DI\Compiler();
$compiler->addExtension('foo', $foo = new FooExtension());
createContainer($compiler, '
bar:
	lorem: ipsum
');

Assert::same( array('lorem' => 'ipsum'), $foo->barConfig );

