<?php

/**
 * Test: Nette\DI\Compiler: removing services.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$container = createContainer(new DI\Compiler, '
services:
	ipsum: no
');

Assert::false($container->hasService('ipsum'));
