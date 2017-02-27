<?php

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class FooExtension extends Nette\DI\CompilerExtension
{
}


$compiler = new DI\Compiler;
$compiler->addExtension(NULL, new FooExtension);
$compiler->addExtension(NULL, new FooExtension);

Assert::count(2, $compiler->getExtensions());
