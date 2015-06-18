<?php

/**
 * Test: Nette\DI\Compiler: service decorators && generated factories
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

interface FooFactory
{

	/**
	 * @return Foo
	 */
	public function create();
}

class Foo
{

}


$compiler = new DI\Compiler;
$compiler->addExtension('decorator', new Nette\DI\Extensions\DecoratorExtension);
$container = createContainer($compiler, '
decorator:
	Foo:
		inject: yes
services:
	foo: {implement: FooFactory}
');


$builder = $compiler->getContainerBuilder();

Assert::true($builder->getDefinition('foo')->getTag('inject'));
