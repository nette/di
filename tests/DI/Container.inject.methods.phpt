<?php

/**
 * Test: Nette\DI\Container and inject methods.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Test1
{
	public $injects;


	function inject(stdClass $obj)
	{
		$this->injects[] = __METHOD__;
	}


	function injectA(stdClass $obj)
	{
		$this->injects[] = __METHOD__;
	}


	protected function injectB(stdClass $obj)
	{
		$this->injects[] = __METHOD__;
	}


	function injectOptional(DateTime $obj = null)
	{
		$this->injects[] = __METHOD__;
	}
}

class Test2 extends Test1
{
	function injectC(stdClass $obj)
	{
		$this->injects[] = __METHOD__;
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setClass('stdClass');


$container = createContainer($builder);

$test = new Test2;
$container->callInjects($test);
Assert::same(['Test1::inject', 'Test1::injectA', 'Test1::injectOptional', 'Test2::injectC'], $test->injects);
