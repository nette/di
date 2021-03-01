<?php

/**
 * Test: Nette\DI\ContainerLoader basic usage.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$cache = new DI\ContainerLoader(getTempDir() . '/subdir');

$key = [1, 2];
$className = $cache->getClassName($key);
Assert::match('Container%[\w]+%', $className);

$container = $cache->load(fn() => "class $className {}", $key);
Assert::type($className, new $container);

$container = $cache->load(function () {}, 'key2');
Assert::type(DI\Container::class, new $container);
