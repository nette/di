<?php

/**
 * Test: Nette\DI\Compiler and loadDefinitionsFromConfig.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$builder = new DI\ContainerBuilder;
$config = (new DI\Config\Adapters\NeonAdapter)->load(__DIR__ . '/files/compiler.parseServices.namespace.neon');
(new DI\Compiler($builder))->loadDefinitionsFromConfig($config['services']);


Assert::true($builder->hasDefinition('comments'));
Assert::true($builder->hasDefinition('articlesList'));
Assert::true($builder->hasDefinition('commentsControl'));
