<?php

/**
 * Test: Nette\DI\Compiler: generated services factories from interfaces with class type hints in parameters.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

interface IShape
{
	public function getName();
}

class Circle implements IShape
{
	public function getName()
	{
		return 'circle';
	}
}

class Triangle implements IShape
{
	public function getName()
	{
		return 'triangle';
	}
}

class Ellipse extends Circle
{
	public function getName()
	{
		return 'ellipse';
	}
}

interface ICircleFactory
{
	/** @return Picture */
	public function create(Circle $shape);
}

interface ITriangleFactory
{
	/** @return Picture */
	public function create(Triangle $shape);
}

interface IEllipseFactory
{
	/** @return Picture */
	public function create(Ellipse $shape);
}

class Picture
{
	public $shape;


	public function __construct(IShape $shape)
	{
		$this->shape = $shape;
	}


	public function getName()
	{
		return $this->shape->getName();
	}
}

$compiler = new DI\Compiler;
$container = createContainer($compiler, 'files/compiler.generatedFactory.polymorphism.neon');

Assert::type(ICircleFactory::class, $container->getService('circle'));
$picture = $container->getService('circle')->create(new Circle);
Assert::type(Picture::class, $picture);
Assert::same('circle', $picture->getName());

Assert::type(ITriangleFactory::class, $container->getService('triangle'));
$picture = $container->getService('triangle')->create(new Triangle);
Assert::type(Picture::class, $picture);
Assert::same('triangle', $picture->getName());

Assert::type(IEllipseFactory::class, $container->getService('ellipse'));
$picture = $container->getService('ellipse')->create(new Ellipse);
Assert::type(Picture::class, $picture);
Assert::same('ellipse', $picture->getName());

Assert::type(ICircleFactory::class, $container->getService('circle'));
$picture = $container->getService('circle')->create(new Ellipse);
Assert::type(Picture::class, $picture);
Assert::same('ellipse', $picture->getName());

if (PHP_VERSION_ID < 70000) {
	Assert::error(function () use ($container) {
		$container->getService('ellipse')->create(new Circle);
	}, E_RECOVERABLE_ERROR);
} else {
	Assert::throws(function () use ($container) {
		$container->getService('ellipse')->create(new Circle);
	}, TypeError::class);
}
