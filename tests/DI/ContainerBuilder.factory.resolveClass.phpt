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
		function createFoo()
		{
			return new Foo();
		}

		/** @return Bar */
		function createBar()
		{
			return new Bar();
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
		static function create()
		{
			return new self;
		}
	}

	class ThisFactory
	{
		/** @return $this */
		static function create()
		{
			return new self;
		}
	}

	class StaticFactory
	{
		/** @return static */
		static function create()
		{
			return new self;
		}
	}
}

namespace
{
	use Nette\DI;
	use Tester\Assert;


	require __DIR__ . '/../bootstrap.php';


	interface StdClassFactory
	{
		function create();
	}


	$builder = new DI\ContainerBuilder;

	$builder->addDefinition('one')
		->setImplement('StdClassFactory')
		->setClass('stdClass');

	$builder->addDefinition('two')
		->setImplement('StdClassFactory')
		->setFactory('@one');

	$builder->addDefinition('three')
		->setImplement('StdClassFactory')
		->setFactory('@one::create'); // alias

	$builder->addDefinition('four')
		->setClass('A\Factory');

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

	Assert::type(A\Foo::class, $container->getByType('A\Foo'));
	Assert::type(B\Bar::class, $container->getByType('B\Bar'));

	Assert::type(C\SelfFactory::class, $container->getByType('C\SelfFactory'));
	Assert::type(C\ThisFactory::class, $container->getByType('C\ThisFactory'));
	Assert::type(C\StaticFactory::class, $container->getByType('C\StaticFactory'));
}
