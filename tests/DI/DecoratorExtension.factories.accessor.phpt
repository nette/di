<?php

/**
 * Test: Nette\DI\Compiler: service decorators && generated factories
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

interface FooAccessor
{

	/**
	 * @return Foo
	 */
	public function get();
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
	FooAccessor:
		tags: [a]
services:
	foo: Foo
	acc: {implement: FooAccessor}
');


$builder = $compiler->getContainerBuilder();

Assert::true($builder->getDefinition('foo')->getTag('inject'));
Assert::null($builder->getDefinition('foo')->getTag('a'));

Assert::null($builder->getDefinition('acc')->getTag('inject'));
Assert::true($builder->getDefinition('acc')->getTag('a'));
