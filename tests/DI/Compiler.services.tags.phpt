<?php

/**
 * Test: Nette\DI\Compiler: services tags.
 */

use Nette\DI,
	Tester\Assert;


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

Assert::same(array(
	'types' => array(
		'stdclass' => array('lorem'),
		'nette\\object' => array('container'),
		'nette\\di\\container' => array('container'),
	),
	'tags' => array(
		'a' => array('lorem' => TRUE),
		'b' => array('lorem' => 'c'),
		'd' => array('lorem' => array('e')),
	),
), $prop->getValue($container) );

Assert::same( array('lorem' => TRUE), $container->findByTag('a') );
Assert::same( array(), $container->findByTag('x') );
