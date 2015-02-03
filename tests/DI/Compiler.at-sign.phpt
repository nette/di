<?php

/**
 * Test: Nette\DI\Compiler: at-sign protector.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	public $arg;

	function __construct($arg)
	{
		$this->arg = $arg;
	}

}

$container = createContainer(new DI\Compiler, '
services:
	foo: Foo( raw(@name) )
');


Assert::same( '@name', $container->getService('foo')->arg );
