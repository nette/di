<?php

/**
 * Test: Nette\DI\Compiler: service decorators && generated factories
 */

declare(strict_types=1);

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
	FooFactory:
		tags: [a]
services:
	foo: {implement: FooFactory}
');


$builder = $compiler->getContainerBuilder();

Assert::true($builder->getDefinition('foo')->getTag('inject'));

Assert::true($builder->getDefinition('foo')->getTag('a'));
