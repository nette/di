<?php

/**
 * Test: Nette\DI\Compiler: services tags.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


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


$prop = $container->getReflection()->getProperty('meta');
$prop->setAccessible(TRUE);

Assert::same([
	'types' => [
		'stdClass' => [1 => ['lorem']],
		'Nette\\Object' => [1 => ['container']],
		'Nette\\DI\\Container' => [1 => ['container']],
	],
	'services' => ['container' => 'Nette\\DI\\Container', 'lorem' => 'stdClass'],
	'tags' => [
		'a' => ['lorem' => TRUE],
		'b' => ['lorem' => 'c'],
		'd' => ['lorem' => ['e']],
	],
	'aliases' => [],
], $prop->getValue($container));

Assert::same(['lorem' => TRUE], $container->findByTag('a'));
Assert::same([], $container->findByTag('x'));
