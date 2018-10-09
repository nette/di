<?php

/**
 * Test: Nette\DI\Compiler and service referencing.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{
	/** @array */
	public $args;


	public function __construct($arg1 = '@foo', $arg2 = '@@foo', $arg3 = '@\stdClass')
	{
		$this->args = func_get_args();
	}
}


$container = createContainer(new DI\Compiler, '
services:
	- stdClass
	a: Lorem(3 = true)
	b: Lorem(3 = Lorem(3 = true))
	c: Lorem(@@test)
');


Assert::same(['@foo', '@@foo', '@\stdClass', true], $container->getService('a')->args);
Assert::equal(['@foo', '@@foo', '@\stdClass', new Lorem('@foo', '@@foo', '@\stdClass', true)], $container->getService('b')->args);
Assert::same(['@test'], $container->getService('c')->args);
