<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


/** @return ClassA */
function func()
{
	return new ClassA;
}

class ClassA
{
	/** @return ClassB */
	function funcA(stdClass $arg)
	{
		return new ClassB;
	}
}

class ClassB
{
	/** @return ClassC */
	function funcB(stdClass $arg)
	{
		return new ClassC;
	}
}

class ClassC
{}


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	std: stdClass
	classA: ::func()
	classB1: @classA::funcA()
	classB2: ::func()::funcA()
	classC: ClassA()::funcA()::funcB()
', 'neon');

Assert::type('ClassA', $container->getService('classA'));
Assert::type('ClassB', $container->getService('classB1'));
Assert::type('ClassB', $container->getService('classB2'));
Assert::type('ClassC', $container->getService('classC'));
