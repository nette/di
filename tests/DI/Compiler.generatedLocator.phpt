<?php

/**
 * Test: Nette\DI\Compiler: generated services locators.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{
}

class LoremChild extends Lorem
{
}

interface Locator
{
	public function get($name): Lorem;
}

interface LocatorN
{
	public function get($name): ?Lorem;
}

interface LocatorFactory
{
	public function create($name): Lorem;
}

interface LocatorFactoryN
{
	public function create($name): ?Lorem;
}


$container = createContainer(new DI\Compiler, '
services:
	- LoremChild

	lorem1:
		type: Lorem
		tags:
			a: 1

	lorem2:
		type: Lorem
		tags:
			a: 2

	lorem3:
		type: Lorem
		tags:
			b: 3

	one: Locator(a: @lorem1, b: LoremChild)
	two: Locator(tagged: a)
	five: LocatorN(tagged: a)
	seven: Locator(a: @lorem1)
	eight: Locator(a: LoremChild())
');


// accessor
$one = $container->getService('one');
Assert::type(Lorem::class, $one->get('a'));
Assert::type(LoremChild::class, $one->get('b'));
Assert::same($one->get('a'), $one->get('a'));

Assert::exception(
	fn() => $one->get('undefined'),
	Nette\DI\MissingServiceException::class,
	"Service 'undefined' is not defined.",
);

// tagged accessor
$two = $container->getService('two');
Assert::same($container->getService('lorem1'), $two->get('1'));
Assert::same($container->getService('lorem2'), $two->get('2'));

Assert::exception(
	fn() => $two->get('3'),
	Nette\DI\MissingServiceException::class,
	"Service '3' is not defined.",
);

// nullable accessor
$five = $container->getService('five');
Assert::type(Lorem::class, $five->get('1'));
Assert::type(Lorem::class, $five->get('2'));
Assert::null($five->get('3'));

// accessor with one service
$one = $container->getService('seven');
Assert::type(Lorem::class, $one->get('a'));

// accessor with custom defined classes
$one = $container->getService('eight');
Assert::type(LoremChild::class, $one->get('a'));
Assert::notSame($container->getByType(LoremChild::class), $one->get('a'));
