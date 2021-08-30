<?php

/**
 * Test: Nette\DI\Compiler: generated services factories from interfaces with class type in parameters.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

interface IShape
{
	public function getName(): string;
}

class Circle implements IShape
{
	public function getName(): string
	{
		return 'circle';
	}
}

class Triangle implements IShape
{
	public function getName(): string
	{
		return 'triangle';
	}
}

class Ellipse extends Circle
{
	public function getName(): string
	{
		return 'ellipse';
	}
}

interface ICircleFactory
{
	public function create(Circle $shape): Picture;
}

interface ITriangleFactory
{
	public function create(Triangle $shape): Picture;
}

interface IEllipseFactory
{
	public function create(Ellipse $shape): Picture;
}

class Picture
{
	public $shape;


	public function __construct(IShape $shape)
	{
		$this->shape = $shape;
	}


	public function getName(): string
	{
		return $this->shape->getName();
	}
}

$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	circle:
		implement: ICircleFactory

	triangle:
		implement: ITriangleFactory

	ellipse:
		implement: IEllipseFactory
');

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

Assert::throws(function () use ($container) {
	$container->getService('ellipse')->create(new Circle);
}, TypeError::class);
