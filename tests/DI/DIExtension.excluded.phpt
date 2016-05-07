<?php

/**
 * Test: DIExtension exluded classes
 */

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$compiler = new DI\Compiler;
$compiler->addExtension('di', new DIExtension);
$container = createContainer($compiler, '
di:
	excluded: [stdClass]

services:
	std: stdClass
', 'neon');

$builder = $compiler->getContainerBuilder();
Assert::null($builder->getByType('stdClass'));
