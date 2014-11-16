<?php

/**
 * Test: Nette\DI\ContainerLoader basic usage.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$cache = new DI\ContainerLoader(TEMP_DIR);

$key = array(1, 2);
$className = $cache->getClassName($key);
Assert::match('Container%[\w]+%', $className);

$container = $cache->load($key, function($class) {
	return array("class $class {}", array());
});
Assert::type($className, new $container);
