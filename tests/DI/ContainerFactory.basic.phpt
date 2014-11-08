<?php

/**
 * Test: Nette\DI\ContainerFactory basic usage.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$factory = new DI\ContainerFactory(TEMP_DIR);

$container = $factory->create();
Assert::type($factory->class, $container);
Assert::type($factory->parentClass, $container);

$container = $factory->create();
Assert::type($factory->class, $container);
Assert::type($factory->parentClass, $container);

$factory->class = 'My';
$container = $factory->create();
Assert::type($factory->class, $container);
Assert::type($factory->parentClass, $container);

$factory->class = 'My2';
$factory->config = array('parameters' => array('foo' => 'a'));
$factory->configFiles = array(
	array(Tester\FileMock::create('parameters: {foo: b}', 'neon'), NULL),
	array(array('parameters' => array('foo' => 'c')), NULL),
);
$container = $factory->create();
Assert::same(array('foo' => 'c'), $container->parameters);
