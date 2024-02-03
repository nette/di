<?php

/**
 * Test: Nette\DI\Container errors usage.
 */

declare(strict_types=1);

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$service = new stdClass;
$container = new Container;

Assert::exception(
	fn() => $container->addService('', $service),
	Nette\InvalidArgumentException::class,
	'Service name must be a non-empty string.',
);

Assert::exception(
	fn() => $container->getService('one'),
	Nette\DI\MissingServiceException::class,
	"Service 'one' not found.",
);

Assert::exception(function () use ($container, $service) {
	$container->addService('one', $service);
	$container->addService('one', $service);
}, Nette\InvalidStateException::class, "Service 'one' already exists.");
