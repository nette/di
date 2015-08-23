<?php

/**
 * Test: Nette\DI\Container expand.
 */

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$container = new Container([
	'appDir' => '/myApp',
	'dirs' => ['cache' => '/temp'],
]);

Assert::same('/myApp/test', $container->expand('%appDir%/test'));
Assert::same('/temp/test', $container->expand('%dirs.cache%/test'));
Assert::same(['cache' => '/temp'], $container->expand('%dirs%'));

Assert::exception(function () use ($container) {
	$container->expand('%bar%');
}, Nette\InvalidArgumentException::class, "Missing parameter 'bar'.");

Assert::exception(function () use ($container) {
	$container->expand('%foo.bar%');
}, Nette\InvalidArgumentException::class, "Missing parameter 'foo.bar'.");

Assert::exception(function () use ($container) {
	$container->parameters['bar'] = [];
	$container->expand('foo%bar%');
}, Nette\InvalidArgumentException::class, "Unable to concatenate non-scalar parameter 'bar' into 'foo%bar%'.");
