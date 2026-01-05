<?php

/**
 * Test: Nette\DI\Compiler: loadConfig with Closure callback
 */

declare(strict_types=1);

use Nette\DI\Compiler;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$compiler = new Compiler;
$compiler->loadConfig('files/closure.config.php');
$code = $compiler->compile();

Assert::contains('closureService', $code);
