<?php

/**
 * Test: Nette\DI\ContainerBuilder and resolving class in generated factories.
 */

declare(strict_types=1);

namespace A
{
	use B\Bar;

	class Factory
	{
		public function createFoo(): Foo
		{
			return new Foo;
		}


		public function createBar(): Bar
		{
			return new Bar;
		}
	}

	class Foo
	{
	}

}

namespace B
{

	class Bar
	{
	}

}

namespace C
{
	class SelfFactory
	{
		public static function create(): self
		{
			return new self;
		}
	}
}

namespace {
	use Nette\DI;
	use Tester\Assert;


	require __DIR__ . '/../bootstrap.php';


	interface StdClassFactory
	{
		public function create(): stdClass;
	}


	$builder = new DI\ContainerBuilder;

	$builder->addFactoryDefinition('one')
		->setImplement(StdClassFactory::class)
		->getResultDefinition()
			->setCreator(stdClass::class);

	$builder->addFactoryDefinition('two')
		->setImplement(StdClassFactory::class)
		->getResultDefinition()
			->setCreator('@eight');

	$builder->addFactoryDefinition('three')
		->setImplement(StdClassFactory::class)
		->getResultDefinition()
			->setCreator('@one::create') // alias
			->setType(stdClass::class); // type is needed

	$builder->addDefinition('four')
		->setType(A\Factory::class);

	$builder->addDefinition('five')
		->setCreator('@four::createFoo');

	$builder->addDefinition('six')
		->setCreator('@four::createBar');

	$builder->addDefinition('seven')
		->setCreator('C\SelfFactory::create');

	$builder->addDefinition('eight')
		->setCreator('stdClass');


	$container = createContainer($builder);

	Assert::type(StdClassFactory::class, $container->getService('one'));

	Assert::type(StdClassFactory::class, $container->getService('two'));
	Assert::type(stdClass::class, $container->getService('two')->create());
	Assert::notSame($container->getService('two')->create(), $container->getService('two')->create());

	Assert::type(StdClassFactory::class, $container->getService('three'));
	Assert::type(stdClass::class, $container->getService('three')->create());
	Assert::notSame($container->getService('three')->create(), $container->getService('three')->create());

	Assert::type(A\Foo::class, $container->getByType(A\Foo::class));
	Assert::type(B\Bar::class, $container->getByType(B\Bar::class));

	Assert::type(C\SelfFactory::class, $container->getByType(C\SelfFactory::class));
}
