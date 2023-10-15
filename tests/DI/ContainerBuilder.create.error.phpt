<?php

/**
 * Test: Nette\DI\ContainerBuilder and errors in factory.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;


require __DIR__ . '/../bootstrap.php';


testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setType('X')->setCreator('Unknown');
}, Nette\InvalidArgumentException::class, "Service 'one': Class or interface 'X' not found.");


testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition(null)->setCreator('Unknown');
	$builder->complete();
}, Nette\DI\ServiceCreationException::class, "Service (Unknown::__construct()): Class 'Unknown' not found.");


testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setCreator('@two');
	$builder->addDefinition('two')->setCreator('Unknown');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'two': Class 'Unknown' not found.");


testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setCreator(new Reference('two'));
	$builder->addDefinition('two')->setCreator('Unknown');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'two': Class 'Unknown' not found.");


testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setCreator('stdClass::foo');
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Method stdClass::foo() is not callable.");


testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setCreator('Nette\DI\Container::foo'); // has __magic
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Method Nette\\DI\\Container::foo() is not callable.");


testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addFactoryDefinition('one')
		->setImplement('Unknown');
}, Nette\InvalidArgumentException::class, "Service 'one': Interface 'Unknown' not found.");



interface Bad4
{
	public function create();
}

testException('', function () {
	$builder = new DI\ContainerBuilder;
	@$builder->addFactoryDefinition('one')
		->setImplement(Bad4::class); // missing type triggers warning
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Bad4): Return type of Bad4::create() is not declared.");


interface Bad5
{
	public function get($arg);
}

testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addAccessorDefinition('one')
		->setImplement(Bad5::class);
	$builder->complete();
}, Nette\InvalidArgumentException::class, "Service 'one': Method Bad5::get() must have no parameters.");


class Bad6
{
	protected function create()
	{
	}
}

testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setCreator('Bad6::create');
	$builder->complete();
}, Nette\DI\ServiceCreationException::class, "Service 'one': Method Bad6::create() is not callable.");


class Bad7
{
	public static function create()
	{
	}
}

testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setCreator('Bad7::create');
	$builder->complete();
}, Nette\DI\ServiceCreationException::class, "Service 'one': Unknown service type, specify it or declare return type of factory method.");


class Bad8
{
	private function __construct()
	{
	}
}

testException('', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setType(Bad8::class);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Bad8): Class Bad8 has private constructor.");


class Good
{
	public function __construct()
	{
	}
}

testException('fail in argument', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setCreator(Good::class, [new Statement('Unknown')]);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Good): Class 'Unknown' not found. (used in Good::__construct())");

testException('fail in argument', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setCreator(Good::class, [new Statement(Bad8::class)]);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Good): Class Bad8 has private constructor. (used in Good::__construct())");


abstract class Bad9
{
	protected function __construct()
	{
	}
}

testException('abstract class cannot be instantiated', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setType(Bad9::class);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Bad9): Class Bad9 is abstract.");


trait Bad10
{
	public function method()
	{
	}
}

testException('trait cannot be instantiated', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setCreator('Bad10::method');
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

testException('autowiring fail', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: ConstructorParam
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of ConstructorParam): Multiple services of type stdClass found: a, b (required by \$x in ConstructorParam::__construct())");


testException('forced autowiring fail', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: ConstructorParam(@\stdClass)
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of ConstructorParam): Multiple services of type stdClass found: a, b (used in ConstructorParam::__construct())");


testException('autowiring fail in chain', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: MethodParam()::foo()
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of MethodParam): Multiple services of type stdClass found: a, b (required by \$x in MethodParam::foo())");


testException('forced autowiring fail in chain', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: MethodParam()::foo(@\stdClass)
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of MethodParam): Multiple services of type stdClass found: a, b (used in foo())");


testException('autowiring fail in argument', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: Good(ConstructorParam())
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (required by \$x in ConstructorParam::__construct()) (used in Good::__construct())");


testException('forced autowiring fail in argument', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: Good(ConstructorParam(@\stdClass))
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in ConstructorParam::__construct())");


testException('autowiring fail in chain in argument', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: Good(MethodParam()::foo())
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (required by \$x in MethodParam::foo()) (used in Good::__construct())");


testException('forced autowiring fail in chain in argument', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad: Good(MethodParam()::foo(@\stdClass))
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in foo())");


testException('forced autowiring fail in property passing', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad:
		create: Good
		setup:
			- $a = @\stdClass
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in @bad::\$a)");


testException('autowiring fail in rich property passing', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad:
		create: Good
		setup:
			- $a = MethodParam()::foo(@\stdClass)
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in foo())");


testException('autowiring fail in method calling', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad:
		create: MethodParam
		setup:
			- foo
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of MethodParam): Multiple services of type stdClass found: a, b (required by \$x in MethodParam::foo())");


testException('forced autowiring fail in method calling', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad:
		create: Good
		setup:
			- bar(@\stdClass)
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in @bad::bar())");


testException('autowiring fail in rich method calling', function () {
	createContainer(new DI\Compiler, '
services:
	a: stdClass
	b: stdClass
	bad:
		create: Good
		setup:
			- bar(MethodParam()::foo(@\stdClass))
');
}, Nette\DI\ServiceCreationException::class, "Service 'bad' (type of Good): Multiple services of type stdClass found: a, b (used in foo())");
