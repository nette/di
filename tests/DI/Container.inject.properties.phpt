<?php

/**
 * Test: Nette\DI\ContainerBuilder and injection into properties.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Attributes\Inject;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IFoo
{
}

class Foo implements IFoo
{
}

class Test1
{
	#[Inject]
	public stdClass $varA;
}

class Test2 extends Test1
{
	#[Inject]
	public stdClass $varC;

	#[Inject]
	public IFoo $varD;
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType(stdClass::class);
$builder->addDefinition('two')
	->setType(Foo::class);


$container = createContainer($builder);

$test = new Test2;
$container->callInjects($test);
Assert::type(stdClass::class, $test->varA);
Assert::type(stdClass::class, $test->varC);
Assert::type(Foo::class, $test->varD);
