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

Assert::exception(function () use ($container, $service) {
	$container->addService(NULL, $service);
}, TypeError::class);

Assert::exception(function () use ($container) {
	$container->addService('one', NULL);
}, Nette\InvalidArgumentException::class, "Service 'one' must be a object, NULL given.");

Assert::exception(function () use ($container) {
	$container->getService('one');
}, Nette\DI\MissingServiceException::class, "Service 'one' not found.");

Assert::exception(function () use ($container, $service) {
	$container->addService('one', $service);
	$container->addService('one', $service);
}, Nette\InvalidStateException::class, "Service 'one' already exists.");
