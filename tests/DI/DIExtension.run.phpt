<?php

/**
 * Test: DIExtension auto run.
 */

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$compiler = new DI\Compiler;
$compiler->addExtension('di', new DIExtension);
$loader = new DI\Config\Loader;
$config = $loader->load(Tester\FileMock::create('
services:
	std: {class: stdClass, tags: [run]}
', 'neon'));

eval($compiler->compile($config, 'Container1'));

$container = new Container1;
Assert::false($container->isCreated('std'));

$container->initialize();
Assert::true($container->isCreated('std'));
