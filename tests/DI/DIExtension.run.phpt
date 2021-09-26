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
	std: {factory: stdClass, tags: [run]}
	- {factory: stdClass, tags: [run]}
', 'neon'));

@eval($compiler->addConfig($config)->setClassName(Container1::class)->compile()); // @ tag is deprecated

$container = new Container1;
Assert::false($container->isCreated('std'));

$container->initialize();
Assert::true($container->isCreated('std'));
