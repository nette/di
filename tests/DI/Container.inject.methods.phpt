<?php

/**
 * Test: Nette\DI\Container and inject methods.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Test1
{
	public $injects;


	public function inject(stdClass $obj)
	{
		$this->injects[] = __METHOD__;
	}


	public function injectA(stdClass $obj)
	{
		$this->injects[] = __METHOD__;
	}


	protected function injectB(stdClass $obj)
	{
		$this->injects[] = __METHOD__;
	}


	public function injectOptional(?DateTime $obj = null)
	{
		$this->injects[] = __METHOD__;
	}
}

class Test2 extends Test1
{
	public function injectC(stdClass $obj)
	{
		$this->injects[] = __METHOD__;
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType(stdClass::class);


$container = createContainer($builder);

$test = new Test2;
$container->callInjects($test);
Assert::same(['Test1::inject', 'Test1::injectA', 'Test1::injectOptional', 'Test2::injectC'], $test->injects);
