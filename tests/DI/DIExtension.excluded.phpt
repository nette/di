<?php

/**
 * Test: DIExtension exluded classes
 */

declare(strict_types=1);

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
');

$builder = $compiler->getContainerBuilder();
Assert::null($builder->getByType(stdClass::class));
