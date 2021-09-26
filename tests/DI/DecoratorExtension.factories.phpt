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
	public function create(): Foo;
}

class Foo
{
	public function testSetup(string $foo): void
	{
	}
}


$compiler = new DI\Compiler;
$compiler->addExtension('foo', new class extends DI\CompilerExtension {
	public function beforeCompile()
	{
		$this->getContainerBuilder()
			->addFactoryDefinition('bar')
			->setImplement(FooFactory::class);
	}
});

$compiler->addExtension('decorator', new Nette\DI\Extensions\DecoratorExtension);
$container = createContainer($compiler, '
decorator:
	Foo:
		inject: yes
		setup:
			- testSetup(foo)
	FooFactory:
		tags: [a]
services:
	foo: {implement: FooFactory}
');


$builder = $compiler->getContainerBuilder();

Assert::true($builder->getDefinition('foo')->getTag(DI\Extensions\InjectExtension::TAG_INJECT));
Assert::true($builder->getDefinition('foo')->getTag('a'));
Assert::count(1, $builder->getDefinition('foo')->getResultDefinition()->getSetup());

Assert::true($builder->getDefinition('bar')->getTag(DI\Extensions\InjectExtension::TAG_INJECT));
Assert::true($builder->getDefinition('bar')->getTag('a'));
Assert::count(1, $builder->getDefinition('bar')->getResultDefinition()->getSetup());
