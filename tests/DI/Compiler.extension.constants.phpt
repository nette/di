<?php

/**
 * Test: Nette\DI\Compiler: constants in config.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$compiler = new DI\Compiler;
$compiler->addExtension('constants', new Nette\DI\Extensions\ConstantsExtension);
$container = createContainer($compiler, '
constants:
	a: hello
	A: WORLD
');
$container->initialize();

Assert::same( "hello", a );
Assert::same( "WORLD", A );
