<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated factories errors.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setType('X')->setFactory('Unknown');
	$builder->complete();
}, Nette\InvalidStateException::class, "Class Unknown used in service 'one' not found.");


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('@two');
	$builder->addDefinition('two')->setFactory('Unknown');
	$builder->complete();
}, Nette\InvalidStateException::class, "Class Unknown used in service 'two' not found.");


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('stdClass::foo');
	$builder->complete();
}, Nette\InvalidStateException::class, "Method stdClass::foo() used in service 'one' is not callable.");


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Nette\DI\Container::foo'); // has __magic
	$builder->complete();
}, Nette\InvalidStateException::class, "Method Nette\\DI\\Container::foo() used in service 'one' is not callable.");


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setImplement('Unknown')->setType('stdClass');
	$builder->complete();
}, Nette\InvalidStateException::class, "Interface Unknown used in service 'one' not found.");


interface Bad1
{
	public static function create();
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setImplement('Bad1')->setFactory('stdClass');
	$builder->complete();
}, Nette\InvalidStateException::class, "Interface Bad1 used in service 'one' must have just one non-static method create() or get().");


interface Bad2
{
	public function createx();
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setImplement('Bad2')->setFactory('stdClass');
	$builder->complete();
}, Nette\InvalidStateException::class, "Interface Bad2 used in service 'one' must have just one non-static method create() or get().");


interface Bad3
{
	public function other();

	public function create();
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setImplement('Bad3')->setFactory('stdClass');
	$builder->complete();
}, Nette\InvalidStateException::class, "Interface Bad3 used in service 'one' must have just one non-static method create() or get().");


interface Bad4
{
	public function create();
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setImplement('Bad4');
	$builder->complete();
}, Nette\InvalidStateException::class, "Method Bad4::create() used in service 'one' has not return type hint or annotation @return.");


interface Bad5
{
	public function get($arg);
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setImplement('Bad5')->setFactory('stdClass');
	$builder->complete();
}, Nette\InvalidStateException::class, "Method Bad5::get() used in service 'one' must have no arguments.");


class Bad6
{
	protected function create()
	{
	}
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Bad6::create');
	$builder->complete();
}, Nette\InvalidStateException::class, "Method Bad6::create() used in service 'one' is not callable.");


class Bad7
{
	public static function create()
	{
	}
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Bad7::create');
	$builder->complete();
}, Nette\DI\ServiceCreationException::class, "Unknown type of service 'one', declare return type of factory method (for PHP 5 use annotation @return)");


class Bad8
{
	private function __construct()
	{
	}
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setType('Bad8');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Class Bad8 has private constructor.");


class Good
{
	public function __construct()
	{
	}
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Good', [new Statement('Bad')]);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Class Bad not found.");

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Good', [new Statement('Bad8')]);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Class Bad8 has private constructor.");


abstract class Bad9
{
	protected function __construct()
	{
	}
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setType('Bad9');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Class Bad9 is abstract.");
