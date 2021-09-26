<?php

/**
 * Test: Nette\DI\ContainerBuilder and injection into properties.
 */

declare(strict_types=1);

use Nette\DI;
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
	/** @inject @var stdClass */
	public $varA;

	/** @var stdClass @inject */
	public $varB;
}

class Test2 extends Test1
{
	/** @var stdClass @inject */
	public $varC;

	/** @var IFoo @inject */
	public $varD;
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
Assert::type(stdClass::class, $test->varB);
Assert::type(stdClass::class, $test->varC);
Assert::type(Foo::class, $test->varD);
