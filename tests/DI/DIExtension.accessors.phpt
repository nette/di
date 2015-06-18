<?php

/**
 * Test: DIExtension accessors.
 */

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$compiler = new DI\Compiler;
$compiler->addExtension('di', new DIExtension);
$loader = new DI\Config\Loader;
$config = $loader->load(Tester\FileMock::create('
di:
	accessors: yes

services:
	std: stdClass
', 'neon'));

eval($compiler->compile($config, 'Container1'));

$container = new Container1;
Assert::type('stdClass', $container->std);

$rc = new ReflectionClass($container);
Assert::truthy(strpos($rc->getDocComment(), '@property stdClass $std'));
