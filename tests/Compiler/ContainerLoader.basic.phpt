<?php declare(strict_types=1);

/**
 * Test: Nette\DI\ContainerLoader basic usage.
 */

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
