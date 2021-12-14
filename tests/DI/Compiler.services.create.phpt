<?php

/**
 * Test: Nette\DI\Compiler: services factories.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	public static function createLorem($arg): Lorem
	{
		return new Lorem(__METHOD__ . ' ' . implode(' ', func_get_args()));
	}
}


class Lorem
{
	public $arg;
	public $foo;


	public function __construct($arg = null)
	{
		$this->arg = $arg;
	}


	public function foo(...$args): self
	{
		$this->foo = $args;
		return $this;
	}
}

class Ipsum
{
	public $arg;


	public function __construct($arg)
	{
		$this->arg = $arg;
	}
}


$container = createContainer(new DI\Compiler, 'files/compiler.services.create.neon');


Assert::type(Ipsum::class, $container->getService('one'));
Assert::same(1, $container->getService('one')->arg);

Assert::type(Ipsum::class, $container->getService('two'));
Assert::same(1, $container->getService('two')->arg);

Assert::type(Lorem::class, $container->getService('three'));
Assert::same('Factory::createLorem 1 2', $container->getService('three')->arg);

Assert::type(Lorem::class, $container->getService('four'));
Assert::same('Factory::createLorem 1', $container->getService('four')->arg);

Assert::type(Lorem::class, $container->getService('five'));
Assert::same('Factory::createLorem 1', $container->getService('five')->arg);

Assert::type(Lorem::class, $container->getService('six'));
Assert::same('Factory::createLorem 1', $container->getService('six')->arg);

Assert::type(Lorem::class, $container->getService('seven'));

Assert::type(Lorem::class, $container->getService('eight'));

Assert::type(Lorem::class, $container->getService('nine'));
Assert::same('Factory::createLorem 1 2', $container->getService('nine')->arg);
Assert::same([], $container->getService('nine')->foo);

Assert::type(Ipsum::class, $container->getService('referencedService'));
Assert::same($container->getService('one'), $container->getService('referencedService'));

Assert::type(Ipsum::class, $container->getService('referencedServiceWithSetup'));
Assert::notSame($container->getService('one'), $container->getService('referencedServiceWithSetup'));

Assert::type(Ipsum::class, $container->getService('calledService'));
Assert::same($container->getService('one'), $container->getService('calledService')); // called without arguments is reference

Assert::type(Ipsum::class, $container->getService('calledServiceWithArgs'));
Assert::notSame($container->getService('one'), $container->getService('calledServiceWithArgs'));

Assert::type(stdClass::class, $container->getByType('\stdClass'));


Assert::type(Ipsum::class, $container->getService('serviceAsParam'));
Assert::type(Ipsum::class, $container->getService('serviceAsParam')->arg);
Assert::same($container->getService('one'), $container->getService('serviceAsParam')->arg);

Assert::type(Ipsum::class, $container->getService('calledServiceAsParam'));
Assert::type(Ipsum::class, $container->getService('calledServiceAsParam')->arg);
Assert::notSame($container->getService('one'), $container->getService('calledServiceAsParam')->arg);

Assert::type(Ipsum::class, $container->getService('calledServiceWithArgsAsParam'));
Assert::type(Ipsum::class, $container->getService('calledServiceWithArgsAsParam')->arg);
Assert::notSame($container->getService('one'), $container->getService('calledServiceWithArgsAsParam')->arg);


Assert::type(Lorem::class, $container->getService('rich1'));
Assert::same(1, $container->getService('rich1')->arg);
Assert::same([], $container->getService('rich1')->foo);

Assert::type(Lorem::class, $container->getService('rich2'));
Assert::type(Ipsum::class, $container->getService('rich2')->arg);
Assert::same($container->getService('one'), $container->getService('rich2')->arg->arg);
Assert::same([1], $container->getService('rich2')->foo);

Assert::type(Lorem::class, $container->getService('rich3'));
Assert::same('Factory::createLorem 1', $container->getService('rich3')->arg);
Assert::same([], $container->getService('rich3')->foo);

Assert::type(Lorem::class, $container->getService('rich4'));
Assert::same('Factory::createLorem 1', $container->getService('rich4')->arg);
Assert::same([], $container->getService('rich4')->foo);
