<?php

/**
 * Test: DIExtension parentClass
 */

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyContainer extends DI\Container
{
}

$compiler = new DI\Compiler;
$compiler->addExtension('di', new DIExtension);
$container = createContainer($compiler, '
di:
	parentClass: MyContainer
', 'neon');

Assert::type(MyContainer::class, $container);
