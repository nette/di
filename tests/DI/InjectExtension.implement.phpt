<?php

/**
 * Test: Nette\DI\Compiler: inject.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface ServiceFactory
{
	public function create(): Service;
}

class Service
{
	public $foo;


	public function injectFoo(stdClass $foo)
	{
		$this->foo = $foo;
	}
}


$compiler = new DI\Compiler;
$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
$container = createContainer($compiler, '
services:
	- stdClass
	sf:
		implement: ServiceFactory
		inject: true
');


$builder = $compiler->getContainerBuilder();

Assert::equal([
	new Statement([new Reference('self'), 'injectFoo'], [new Reference('01')]),
], $builder->getDefinition('sf')->getResultDefinition()->getSetup());
