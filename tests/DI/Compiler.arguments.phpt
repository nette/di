<?php

/**
 * Test: Nette\DI\Compiler: arguments in config.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{
	public const DolorSit = 10;

	public $args;

	public $var = 123;


	public function __construct()
	{
		$this->args[] = func_get_args();
	}


	public function method()
	{
		$this->args[] = func_get_args();
	}


	public function add($a, $b)
	{
		return $a + $b;
	}
}


define('MyConstantTest', 'one');

$container = createContainer(new DI\Compiler, "
services:
	lorem:
		create: Lorem(::MyConstantTest, Lorem::DolorSit, NOT_CONSTANT_TEST)
		setup:
			- method( @lorem, @self, @container )
			- method( @lorem::add(1, 2), [x: ::strtoupper('hello')] )
			- method( [Lorem, method], 'Lorem::add', Lorem::add )
			- method( not(true) )
			- method( @lorem::var, @self::var, @container::parameters )
			- method( @lorem::DolorSit, @self::DolorSit )
");

$container->parameters = ['something'];

$lorem = $container->getService('lorem');

// constants
Assert::same(['one', Lorem::DolorSit, 'NOT_CONSTANT_TEST'], $lorem->args[0]);

// services
Assert::same([$lorem, $lorem, $container], $lorem->args[1]);

// statements
Assert::same([3, ['x' => 'HELLO']], $lorem->args[2]);

// non-statements
Assert::same([['Lorem', 'method'], 'Lorem::add', 'Lorem::add'], $lorem->args[3]);

// special
Assert::same([false], $lorem->args[4]);

// service variables
Assert::same([$lorem->var, $lorem->var, $container->parameters], $lorem->args[5]);

// service constant
Assert::same([Lorem::DolorSit, Lorem::DolorSit], $lorem->args[6]);
