<?php

/**
 * Test: Nette\DI\Compiler: removing services.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$container = createContainer(new DI\Compiler, '
services:
	ipsum: no
');

Assert::false($container->hasService('ipsum'));
