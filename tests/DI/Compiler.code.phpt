<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$loader = new Nette\DI\Config\Loader;
$config = $loader->load(Tester\FileMock::create('
services:
	- stdClass
	name: stdClass
', 'neon'));

$compiler = new Nette\DI\Compiler;
$code = $compiler->addConfig($config)
	->compile();

Assert::matchFile(
	__DIR__ . '/expected/compiler.code.php',
	$code,
);
