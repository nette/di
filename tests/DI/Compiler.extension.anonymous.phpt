<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class FooExtension extends Nette\DI\CompilerExtension
{
}


$compiler = new DI\Compiler;
$compiler->addExtension(null, new FooExtension);
$compiler->addExtension(null, new FooExtension);

Assert::count(4, $compiler->getExtensions());
