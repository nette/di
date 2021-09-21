<?php

/**
 * Test: Nette\DI\Container dynamic usage.
 * @phpVersion 8.0
 */

declare(strict_types=1);

use Nette\DI\Container;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$container = new Container;

// union type
Assert::exception(function () use ($container) {
	@$container->addService('six', function (): \stdClass|\Closure {}); // @ triggers service should be defined as "imported"
	$container->getService('six');
}, Nette\InvalidStateException::class, 'The {closure}%a?% is not expected to have a union%a?% type.');
