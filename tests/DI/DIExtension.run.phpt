<?php

/**
 * Test: DIExtension auto run.
 */

declare(strict_types=1);

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

eval($compiler->addConfig($config)->setClassName('Container1')->compile());

$container = new Container1;
Assert::false($container->isCreated('std'));

$container->initialize();
Assert::true($container->isCreated('std'));
