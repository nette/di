<?php

/**
 * Test: Working with user defined services in CompilerExtension.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IBar
{
}
interface IIpsum
{
}
interface IIpsumFactory
{
	/** @return IIpsum */
	public function create();
}
interface IFooBar
{
}

class Foo
{
}
class Bar implements IBar
{
}
class Lorem
{
}
class Ipsum implements IIpsum
{
}
class FooBar implements IFooBar
{
}

class Factory
{
	/**
	 * @return Lorem
	 */
	public static function createLorem()
	{
		return new Lorem;
	}
}

class FooExtension extends Nette\DI\CompilerExtension
{
	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();

		if (!$builder->getByType(Foo::class)) {
			Assert::fail('Foo service should be defined.');
		}
		if (!$builder->getByType(IBar::class)) {
			Assert::fail('IBar service should be defined.');
		}
		if (!$builder->getByType(Lorem::class)) {
			Assert::fail('Lorem service should be defined.');
		}
		if (!$builder->getByType(IIpsumFactory::class)) {
			Assert::fail('IIpsumFactory service should be defined.');
		}

		if (!$builder->getByType(FooBar::class)) {
			$builder->addDefinition('five')->setType(FooBar::class);
		}
	}
}


$loader = new DI\Config\Loader;
$compiler = new DI\Compiler;
$extension = new FooExtension;
$compiler->addExtension('database', $extension);

$container = createContainer($compiler, '
services:
	one: Foo
	two: Bar
	three: Factory::createLorem()
	four:
		type: Ipsum
		implement: IIpsumFactory
');


Assert::type(Foo::class, $container->getService('one'));
Assert::type(Bar::class, $container->getService('two'));
Assert::type(Lorem::class, $container->getService('three'));
Assert::type(IIpsumFactory::class, $container->getService('four'));

Assert::type(FooBar::class, $container->getByType(IFooBar::class));
