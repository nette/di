<?php declare(strict_types=1);

/**
 * Test: Overriding class of service definition defined in CompilerExtension.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	public static function createLorem(...$args): Lorem
	{
		return new Lorem(...$args);
	}
}


class IpsumFactory
{
	public static function create(...$args): Ipsum
	{
		return new Ipsum(...$args);
	}
}


class Lorem
{
	public array $constructorArgs;


	public function __construct(...$args)
	{
		$this->constructorArgs = $args;
	}
}


class Ipsum
{
	public array $constructorArgs;


	public function __construct(...$args)
	{
		$this->constructorArgs = $args;
	}
}


class FooExtension extends Nette\DI\CompilerExtension
{
	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition('one1')
			->setCreator(Lorem::class, [1]);
		$builder->addDefinition('one2')
			->setCreator(Lorem::class, [1]);
		$builder->addDefinition('one3')
			->setCreator(Lorem::class, [1]);
		$builder->addDefinition('one4')
			->setCreator(Lorem::class, [1]);
		$builder->addDefinition('one5')
			->setCreator(Lorem::class, [1]);
		$builder->addDefinition('one6')
			->setCreator(Lorem::class, [1]);
		$builder->addDefinition('one7')
			->setCreator(Lorem::class, [1]);
		$builder->addDefinition('one8')
			->setCreator(Lorem::class, [1])
			->addSetup('__construct', [2]);
		$builder->addDefinition('one9')
			->setCreator(Lorem::class, [1]);
		$builder->addDefinition('one10')
			->setCreator(Lorem::class, [1]);

		$builder->addDefinition('two1')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('two2')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('two3')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('two4')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1, 2]);
		$builder->addDefinition('two5')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('two6')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1, 2]);
		$builder->addDefinition('two7')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('two8')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1, 2]);
		$builder->addDefinition('two9')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1, 2]);
		$builder->addDefinition('two10')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('two11')
			->setType(Lorem::class)
			->setCreator('Factory::createLorem', [1]);

		$builder->addDefinition('three1')
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('three2')
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('three3')
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('three4')
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('three5')
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('three6')
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('three7')
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('three8')
			->setCreator('Factory::createLorem', [1]);
		$builder->addDefinition('three9')
			->setCreator('Factory::createLorem', [1]);
	}
}


$compiler = new DI\Compiler;
$extension = new FooExtension;
$compiler->addExtension('database', $extension);
$container = createContainer($compiler, 'files/compiler.extensionOverride.neon');


Assert::type(Ipsum::class, $service = $container->getService('one1'));
Assert::same([], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('one2'));
Assert::same([2], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('one3'));
Assert::same([2], $service->constructorArgs);

Assert::type(Lorem::class, $service = $container->getService('one4'));
Assert::same([2], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('one5'));
Assert::same([], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('one6'));
Assert::same([2], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('one7'));
Assert::same([2], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('one8'));
Assert::same([], $service->constructorArgs);

Assert::exception(
	fn() => $container->getService('one9'),
	TypeError::class,
	'%a% must be %a% Ipsum,%a?% Lorem returned',
);

Assert::type(Ipsum::class, $service = $container->getService('one10'));
Assert::same([], $service->constructorArgs);


Assert::type(Ipsum::class, $service = $container->getService('two1'));
Assert::same([], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('two2'));
Assert::same([2], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('two3'));
Assert::same([2], $service->constructorArgs);

Assert::type(Lorem::class, $service = $container->getService('two4'));
Assert::same([2], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('two5'));
Assert::same([], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('two6'));
Assert::same([2], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('two7'));
Assert::same([2], $service->constructorArgs);

Assert::type(Lorem::class, $service = $container->getService('two8'));
Assert::same([1, 'new'], $service->constructorArgs);

Assert::type(Lorem::class, $service = $container->getService('two9'));
Assert::same(['new'], $service->constructorArgs);

Assert::type(Lorem::class, $service = $container->getService('two10'));
Assert::same([2, 'new'], $service->constructorArgs);

Assert::exception(
	fn() => $container->getService('two11'),
	TypeError::class,
	'%a% must be %a% Ipsum,%a?% Lorem returned',
);

Assert::type(Ipsum::class, $service = $container->getService('two12'));
Assert::same([], $service->constructorArgs);



Assert::type(Ipsum::class, $service = $container->getService('three1'));
Assert::same([], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('three2'));
Assert::same([2], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('three3'));
Assert::same([2], $service->constructorArgs);

Assert::type(Lorem::class, $service = $container->getService('three4'));
Assert::same([2], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('three5'));
Assert::same([], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('three6'));
Assert::same([2], $service->constructorArgs);

Assert::type(Ipsum::class, $service = $container->getService('three7'));
Assert::same([2], $service->constructorArgs);

Assert::exception(
	fn() => $container->getService('three8'),
	TypeError::class,
	'%a% must be %a% Ipsum,%a?% Lorem returned',
);

Assert::type(Ipsum::class, $service = $container->getService('three9'));
Assert::same([], $service->constructorArgs);
