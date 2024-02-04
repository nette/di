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
			->addFactoryDefinition('fac1')
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
	fac2: {implement: FooFactory}
');


$builder = $compiler->getContainerBuilder();

Assert::true($builder->getDefinition('fac1')->getTag('a'));
Assert::count(1, $builder->getDefinition('fac1')->getResultDefinition()->getSetup());
Assert::true($builder->getDefinition('fac1')->getResultDefinition()->getTag(DI\Extensions\InjectExtension::TagInject));

Assert::true($builder->getDefinition('fac2')->getTag('a'));
Assert::count(1, $builder->getDefinition('fac2')->getResultDefinition()->getSetup());
Assert::true($builder->getDefinition('fac2')->getResultDefinition()->getTag(DI\Extensions\InjectExtension::TagInject));
