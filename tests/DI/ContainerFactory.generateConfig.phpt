<?php

/**
 * Test: Nette\DI\Compiler and addExtension on loadConfiguration stage.
 */
use Nette\DI\ContainerFactory,
	Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$testNeon = <<<EOT
foo: bar
EOT;

$files1 = array(
	array(Tester\FileMock::create($testNeon, 'neon'), NULL),
	array(array('foo' => 'fromFile'), NULL)
);

$files2 = array(
	array(array('bar' => 'fromFile'), NULL)
);

$containerFactory = new Nette\DI\ContainerFactory(NULL);
$containerFactory->config = array('foo' => 'baz');

$m = $containerFactory->getReflection()->getMethod('generateConfig');
$m->setAccessible(TRUE);

$containerFactory->configFiles = $files1;
Assert::equal($m->invoke($containerFactory), array('foo' => 'fromFile'));

$containerFactory->configFiles = $files2;
Assert::equal($m->invoke($containerFactory), array('foo' => 'baz', 'bar' => 'fromFile'));
