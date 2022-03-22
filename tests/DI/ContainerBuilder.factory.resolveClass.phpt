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
		/** @return Foo */
		public function createFoo()
		{
			return new Foo;
		}


		/** @return Bar */
		public function createBar()
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
		/** @return self */
		public static function create()
		{
			return new self;
		}
	}

	class ThisFactory
	{
		/** @return $this */
		public static function create()
		{
			return new self;
		}
	}

	class StaticFactory
	{
		/** @return static */
		public static function create()
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
		public function create();
	}


	$builder = new DI\ContainerBuilder;

	$builder->addFactoryDefinition('one')
		->setImplement(StdClassFactory::class)
		->getResultDefinition()
			->setFactory(stdClass::class);

	$builder->addFactoryDefinition('two')
		->setImplement(StdClassFactory::class)
		->getResultDefinition()
			->setFactory('@one');

	$builder->addFactoryDefinition('three')
		->setImplement(StdClassFactory::class)
		->getResultDefinition()
			->setFactory('@one::create') // alias
			->setType(stdClass::class); // type is needed

	$builder->addDefinition('four')
		->setType(A\Factory::class);

	$builder->addDefinition('five')
		->setFactory('@four::createFoo');

	$builder->addDefinition('six')
		->setFactory('@four::createBar');

	$builder->addDefinition('seven')
		->setFactory('C\SelfFactory::create');

	$builder->addDefinition('eight')
		->setFactory('C\ThisFactory::create');

	$builder->addDefinition('nine')
		->setFactory('C\StaticFactory::create');


	$container = createContainer($builder);

	Assert::type(StdClassFactory::class, $container->getService('one'));

	Assert::type(StdClassFactory::class, $container->getService('two'));
	Assert::type(StdClassFactory::class, $container->getService('two')->create());
	Assert::notSame($container->getService('two')->create(), $container->getService('two')->create());

	Assert::type(StdClassFactory::class, $container->getService('three'));
	Assert::type(stdClass::class, $container->getService('three')->create());
	Assert::notSame($container->getService('three')->create(), $container->getService('three')->create());

	Assert::type(A\Foo::class, $container->getByType(A\Foo::class));
	Assert::type(B\Bar::class, $container->getByType(B\Bar::class));

	Assert::type(C\SelfFactory::class, $container->getByType(C\SelfFactory::class));
	Assert::type(C\ThisFactory::class, $container->getByType(C\ThisFactory::class));
	Assert::type(C\StaticFactory::class, $container->getByType(C\StaticFactory::class));
}
