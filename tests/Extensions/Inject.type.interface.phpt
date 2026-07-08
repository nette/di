<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler: inject.
 */

use Nette\DI;
use Nette\DI\Expressions\Call;
use Nette\DI\Expressions\Reference;
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
	new Call(new Reference('self'), 'injectFoo'),
], $builder->getDefinition('one')->getSetup());
