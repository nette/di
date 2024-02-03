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

interface Locator
{
	public function getA(): Lorem;

	public function getB(): ?Lorem;

	public function createC(): Lorem;

	public function createD(): ?Lorem;
}


$container = createContainer(new DI\Compiler, '
services:
	lorem1: Lorem

	one: Locator(a: Lorem, b: Lorem, c: Lorem, d: Lorem)
	two: Locator(tagged: a)
');


// accessor
$one = $container->getService('one');
Assert::type(Lorem::class, $one->getA());
Assert::type(Lorem::class, $one->getB());
Assert::same($one->getA(), $one->getB());

// factory
Assert::type(Lorem::class, $one->createC());
Assert::type(Lorem::class, $one->createD());
Assert::notSame($one->createC(), $one->createD());

// nullable
$two = $container->getService('two');
Assert::null($two->getB());
Assert::null($two->createD());

// undefined
Assert::exception(
	fn() => $two->getA(),
	Nette\DI\MissingServiceException::class,
	'Service is not defined.',
);

Assert::exception(
	fn() => $two->createC(),
	Nette\DI\MissingServiceException::class,
	'Service is not defined.',
);
