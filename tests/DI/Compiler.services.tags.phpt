<?php

/**
 * Test: Nette\DI\Compiler: services tags.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	lorem:
		factory: stdClass
		tags:
			- a
			b: c
			d: [e]
');


$prop = (new ReflectionClass($container))->getProperty('meta');
$prop->setAccessible(true);

Assert::same([
	'types' => [
		'stdClass' => [1 => ['lorem']],
		Nette\DI\Container::class => [1 => ['container']],
	],
	'services' => ['container' => Nette\DI\Container::class, 'lorem' => 'stdClass'],
	'tags' => [
		'a' => ['lorem' => true],
		'b' => ['lorem' => 'c'],
		'd' => ['lorem' => ['e']],
	],
	'aliases' => [],
], $prop->getValue($container));

Assert::same(['lorem' => true], $container->findByTag('a'));
Assert::same([], $container->findByTag('x'));
