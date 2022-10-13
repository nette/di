<?php

/**
 * Test: Nette\DI\Compiler: services setup.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{
	public $test;
	public $arr;


	public function test(...$args)
	{
		Notes::add(__METHOD__ . ' ' . implode(' ', $args));
	}
}


class Ipsum
{
	public static $staticTest;

	public static $instances;

	public $args;
	public $test;


	public function __construct(...$args)
	{
		$this->args = $args;
		self::$instances[] = $this;
	}


	public function test(...$args)
	{
		Notes::add(__METHOD__ . ' ' . implode(' ', $args) . ' ' . implode(' ', $this->args));
	}


	public static function staticTest(...$args)
	{
		Notes::add(__METHOD__ . ' ' . implode(' ', $args));
	}
}


function globtest(...$args)
{
	Notes::add(__METHOD__ . ' ' . implode(' ', $args));
}


$container = createContainer(new DI\Compiler, 'files/compiler.services.setup.neon');


Assert::same([
], Notes::fetch());

Assert::type(Lorem::class, $container->getService('lorem'));

Assert::same([
	'Lorem::test 2',
	'Lorem::test 3',
	'Lorem::test 4',
	'Ipsum::staticTest 5',
	'Ipsum::test 6 ',
	'globtest 7',
	'Ipsum::test  a',
	'Ipsum::test 10 b',
], Notes::fetch());

Assert::same(8, $container->getService('lorem')->test);
Assert::same(9, Ipsum::$staticTest);
Assert::equal(new Lorem, $container->getService('ipsum')->test);
Assert::same([1, 2], $container->getService('lorem')->arr);

Assert::count(4, Ipsum::$instances);
Assert::same([$container->getService('lorem')], Ipsum::$instances[3]->args);
