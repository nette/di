<?php

/**
 * Test: Nette\DI\Compiler: nonshared services factories.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Ipsum
{
}

class Lorem
{
}


$container = createContainer(new DI\Compiler, "
parameters:
	'false': false

services:
	ipsum:
		factory: Ipsum

	lorem:
		class: Lorem
		parameters: [Ipsum foo, bar: %false%]
		setup:
			- test(%foo%, %bar%)
");


Assert::true($container->hasService('lorem'));
Assert::true(method_exists($container, 'createServiceLorem'));

$params = new ReflectionParameter([$container, 'createServiceLorem'], 0);
Assert::same('foo', $params->getName());
Assert::same('Ipsum', $params->getClass()->getName());
Assert::false($params->isDefaultValueAvailable());

$params = new ReflectionParameter([$container, 'createServiceLorem'], 1);
Assert::same('bar', $params->getName());
Assert::false($params->getDefaultValue());
