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
	const DOLOR_SIT = 10;

	public $args;

	public $var = 123;


	function __construct()
	{
		$this->args[] = func_get_args();
	}


	function method()
	{
		$this->args[] = func_get_args();
	}


	function add($a, $b)
	{
		return $a + $b;
	}
}

define('MY_CONSTANT_TEST', 'one');


Assert::error(function () use (&$container) {
	$container = createContainer(new DI\Compiler, "
	services:
		lorem:
			class: Lorem(::MY_CONSTANT_TEST, Lorem::DOLOR_SIT, MY_FAILING_CONSTANT_TEST)
			setup:
				- method( @lorem, @self, @container )
				- method( @lorem::add(1, 2), [x: ::strtoupper('hello')] )
				- method( [Lorem, method], 'Lorem::add', Lorem::add )
				- method( not(TRUE) )
				- method( @lorem::var, @self::var, @container::parameters )
				- method( @lorem::DOLOR_SIT, @self::DOLOR_SIT, @container::TAGS )

		dolor:
			class: Lorem(::MY_FAILING_CONSTANT_TEST)
	");
}, E_WARNING, "%a?%Couldn't find constant MY_FAILING_CONSTANT_TEST");

$container->parameters = ['something'];

$lorem = $container->getService('lorem');
$dolor = $container->getService('dolor');

// constants
Assert::same(['one', Lorem::DOLOR_SIT, 'MY_FAILING_CONSTANT_TEST'], $lorem->args[0]);
Assert::same([NULL], $dolor->args[0]);

// services
Assert::same([$lorem, $lorem, $container], $lorem->args[1]);

// statements
Assert::same([3, ['x' => 'HELLO']], $lorem->args[2]);

// non-statements
Assert::same([['Lorem', 'method'], 'Lorem::add', 'Lorem::add'], $lorem->args[3]);

// special
Assert::same([FALSE], $lorem->args[4]);

// service variables
Assert::same([$lorem->var, $lorem->var, $container->parameters], $lorem->args[5]);

// service constant
Assert::same([Lorem::DOLOR_SIT, Lorem::DOLOR_SIT, DI\Container::TAGS], $lorem->args[6]);
