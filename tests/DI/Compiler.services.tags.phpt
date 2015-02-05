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
		'stdClass' => array(1 => array('lorem')),
		'Nette\\Object' => array(1 => array('container')),
		'Nette\\DI\\Container' => array(1 => array('container')),
	),
	'services' => array('container' => 'Nette\\DI\\Container', 'lorem' => 'stdClass'),
	'tags' => array(
		'a' => array('lorem' => TRUE),
		'b' => array('lorem' => 'c'),
		'd' => array('lorem' => array('e')),
	),
	'aliases' => array(),
), $prop->getValue($container) );

Assert::same( array('lorem' => TRUE), $container->findByTag('a') );
Assert::same( array(), $container->findByTag('x') );
