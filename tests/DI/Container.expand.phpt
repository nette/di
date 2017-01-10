<?php

/**
 * Test: Nette\DI\Container expand.
 */

declare(strict_types=1);

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$container = new Container([
	'appDir' => '/myApp',
	'dirs' => ['cache' => '/temp'],
]);

Assert::same('/myApp/test', @$container->expand('%appDir%/test')); // @ deprecated
Assert::same('/temp/test', @$container->expand('%dirs.cache%/test')); // @ deprecated
Assert::same(['cache' => '/temp'], @$container->expand('%dirs%')); // @ deprecated

Assert::exception(function () use ($container) {
	@$container->expand('%bar%'); // @ deprecated
}, Nette\InvalidArgumentException::class, "Missing parameter 'bar'.");

Assert::exception(function () use ($container) {
	@$container->expand('%foo.bar%'); // @ deprecated
}, Nette\InvalidArgumentException::class, "Missing parameter 'foo.bar'.");

Assert::exception(function () use ($container) {
	$container->parameters['bar'] = [];
	@$container->expand('foo%bar%'); // @ deprecated
}, Nette\InvalidArgumentException::class, "Unable to concatenate non-scalar parameter 'bar' into 'foo%bar%'.");
