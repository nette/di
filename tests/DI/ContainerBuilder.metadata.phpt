<?php

/**
 * Test: Nette\DI\Compiler: services tags.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


function getPropertyValue($obj, string $name)
{
	$prop = (new ReflectionObject($obj))->getProperty($name);
	$prop->setAccessible(true);
	return $prop->getValue($obj);
}


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	lorem:
		create: stdClass
		tags:
			- a
			b: c
			d: [e]
');


Assert::same(
	[
		Nette\DI\Container::class => [['container']],
		stdClass::class => [['lorem']],
	],
	getPropertyValue($container, 'wiring'),
);

Assert::same(
	['container' => Nette\DI\Container::class],
	getPropertyValue($container, 'types'),
);

Assert::same(
	[
		'a' => ['lorem' => true],
		'b' => ['lorem' => 'c'],
		'd' => ['lorem' => ['e']],
	],
	getPropertyValue($container, 'tags'),
);

Assert::same(
	[],
	getPropertyValue($container, 'aliases'),
);

Assert::same(['lorem' => true], $container->findByTag('a'));
Assert::same([], $container->findByTag('x'));
