<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


function func(): ClassA
{
	return new ClassA;
}


class ClassA
{
	public function funcA(stdClass $arg): ClassB
	{
		return new ClassB;
	}
}

class ClassB
{
	public function funcB(stdClass $arg): ClassC
	{
		return new ClassC;
	}
}

class ClassC
{
}


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	std: stdClass
	classA: ::func()
	classB1: @classA::funcA()
	classB2: ::func()::funcA()
	classC: ClassA()::funcA()::funcB()
');

Assert::type(ClassA::class, $container->getService('classA'));
Assert::type(ClassB::class, $container->getService('classB1'));
Assert::type(ClassB::class, $container->getService('classB2'));
Assert::type(ClassC::class, $container->getService('classC'));
