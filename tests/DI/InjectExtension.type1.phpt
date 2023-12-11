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


interface IService
{
}

class Service implements IService
{
	public function injectFoo()
	{
	}
}


$compiler = new DI\Compiler;
$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
$container = createContainer($compiler, '
services:
	one:
		type: IService
		create: Service
		inject: true
');


$builder = $compiler->getContainerBuilder();

Assert::equal([
	new Statement([new Reference('self'), 'injectFoo']),
], $builder->getDefinition('one')->getSetup());
