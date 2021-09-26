<?php

/**
 * Test: Nette\DI\Compiler: generated services accessors.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{
}

interface ILoremAccessor
{
	public function get(): Lorem;
}


$container = createContainer(new DI\Compiler, '
services:
	lorem: Lorem

	lorem2:
		factory: Lorem
		autowired: no

	one: ILoremAccessor
	two: ILoremAccessor()
	three: ILoremAccessor(@lorem2)
	four: ILoremAccessor(@\Lorem)
');


Assert::type(Lorem::class, $container->getService('lorem'));
Assert::notSame($container->getService('lorem'), $container->getService('lorem2'));

Assert::type(ILoremAccessor::class, $container->getService('one'));
Assert::same($container->getService('one')->get(), $container->getService('lorem'));

Assert::type(ILoremAccessor::class, $container->getService('two'));
Assert::same($container->getService('two')->get(), $container->getService('lorem'));

Assert::type(ILoremAccessor::class, $container->getService('three'));
Assert::same($container->getService('three')->get(), $container->getService('lorem2'));

Assert::type(ILoremAccessor::class, $container->getService('four'));
Assert::same($container->getService('four')->get(), $container->getService('lorem'));
