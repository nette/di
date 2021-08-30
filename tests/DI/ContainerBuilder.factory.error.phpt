<?php

/**
 * Test: Nette\DI\ContainerBuilder and errors in factory.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setType('X')->setFactory('Unknown');
}, Nette\InvalidArgumentException::class, "Service 'one': Class or interface 'X' not found.");


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition(null)->setFactory('Unknown');
	$builder->complete();
}, Nette\DI\ServiceCreationException::class, "Service (Unknown::__construct()): Class 'Unknown' not found.");


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('@two');
	$builder->addDefinition('two')->setFactory('Unknown');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'two': Class 'Unknown' not found.");


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory(new Reference('two'));
	$builder->addDefinition('two')->setFactory('Unknown');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'two': Class 'Unknown' not found.");


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('stdClass::foo');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Method stdClass::foo() is not callable.");


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Nette\DI\Container::foo'); // has __magic
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Method Nette\\DI\\Container::foo() is not callable.");


Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addFactoryDefinition('one')
		->setImplement('Unknown');
}, Nette\InvalidArgumentException::class, "Service 'one': Interface 'Unknown' not found.");



interface Bad4
{
	public function create();
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addFactoryDefinition('one')
		->setImplement('Bad4');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Bad4): Method create() has no return type or annotation @return.");


interface Bad5
{
	public function get($arg);
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addAccessorDefinition('one')
		->setImplement('Bad5');
	$builder->complete();
}, Nette\InvalidArgumentException::class, "Service 'one': Method Bad5::get() must have no parameters.");


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
}, Nette\DI\ServiceCreationException::class, "Service 'one': Method Bad6::create() is not callable.");


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
}, Nette\DI\ServiceCreationException::class, "Service 'one': Unknown service type, specify it or declare return type of factory.");


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
}, Nette\InvalidStateException::class, "Service 'one' (type of Bad8): Class Bad8 has private constructor.");


class Good
{
	public function __construct()
	{
	}
}

// fail in argument
Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Good', [new Statement('Unknown')]);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Good): Class 'Unknown' not found. (used in __construct())");

// fail in argument
Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Good', [new Statement('Bad8')]);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Good): Class Bad8 has private constructor. (used in __construct())");


abstract class Bad9
{
	protected function __construct()
	{
	}
}

// abstract class cannot be instantiated
Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setType('Bad9');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Bad9): Class Bad9 is abstract.");


trait Bad10
{
	public function method()
	{
	}
}

// trait cannot be instantiated
Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Bad10::method');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Method Bad10::method() is not callable.");


class ConstructorParam
{
	public function __construct(stdClass $x)
	{
	}
}

class MethodParam
{
	public function foo(stdClass $x): self
	{
	}
}

// autowiring fail
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: ConstructorParam
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of ConstructorParam): Multiple services of type stdClass found: a, b (required by \$x in __construct())");


// forced autowiring fail
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: ConstructorParam(@\stdClass)
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of ConstructorParam): Multiple services of type stdClass found: a, b (used in __construct())");


// autowiring fail in chain
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: MethodParam()::foo()
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of MethodParam): Multiple services of type stdClass found: a, b (required by \$x in foo())");


// forced autowiring fail in chain
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: MethodParam()::foo(@\stdClass)
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of MethodParam): Multiple services of type stdClass found: a, b (used in foo())");


// autowiring fail in argument
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: Good(ConstructorParam())
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (required by \$x in ConstructorParam::__construct()) (used in __construct())");


// forced autowiring fail in argument
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: Good(ConstructorParam(@\stdClass))
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in ConstructorParam::__construct())");


// autowiring fail in chain in argument
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: Good(MethodParam()::foo())
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (required by \$x in MethodParam::foo()) (used in __construct())");


// forced autowiring fail in chain in argument
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: Good(MethodParam()::foo(@\stdClass))
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in foo())");


// forced autowiring fail in property passing
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad:
		factory: Good
		setup:
			- $a = @\stdClass
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in @bad::\$a)");


// autowiring fail in rich property passing
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad:
		factory: Good
		setup:
			- $a = MethodParam()::foo(@\stdClass)
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in foo())");


// autowiring fail in method calling
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad:
		factory: MethodParam
		setup:
			- foo
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of MethodParam): Multiple services of type stdClass found: a, b (required by \$x in foo())");


// forced autowiring fail in method calling
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad:
		factory: Good
		setup:
			- bar(@\stdClass)
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in @bad::bar())");


// autowiring fail in rich method calling
Assert::exception(function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad:
		factory: Good
		setup:
			- bar(MethodParam()::foo(@\stdClass))
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in foo())");
