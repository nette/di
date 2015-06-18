<?php

/**
 * Test: Nette\DI\Compiler: services setup.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{
	function test($arg)
	{
		Notes::add(__METHOD__ . ' ' . $arg);
	}
}


class Ipsum
{
	public static $staticTest;

	public static $instances;

	public $test;

	function __construct($arg = NULL)
	{
		$this->arg = $arg;
		self::$instances[] = $this;
	}

	function test($arg = NULL)
	{
		Notes::add(__METHOD__ . ' ' . $arg . ' ' . $this->arg);
	}

	static function staticTest($arg = NULL)
	{
		Notes::add(__METHOD__ . ' ' . $arg);
	}
}


function globtest($arg)
{
	Notes::add(__METHOD__ . ' ' . $arg);
}


$container = createContainer(new DI\Compiler, 'files/compiler.services.setup.neon');


Assert::same(array(
), Notes::fetch());

Assert::type('Lorem', $container->getService('lorem'));

Assert::same(array(
	'Lorem::test 2',
	'Lorem::test 3',
	'Lorem::test 4',
	'Ipsum::staticTest 5',
	'Ipsum::test 6 ',
	'globtest 7',
	'Ipsum::test  a',
	'Ipsum::test 10 b',
), Notes::fetch());

Assert::same(8, $container->getService('lorem')->test);
Assert::same(9, Ipsum::$staticTest);
Assert::equal(new Lorem, $container->getService('ipsum')->test);

Assert::count(4, Ipsum::$instances);
Assert::same($container->getService('lorem'), Ipsum::$instances[3]->arg);
