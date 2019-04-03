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

Assert::error(function () {
	$container = new Container;
	$container->addService('one', new stdClass);
}, E_USER_NOTICE, "Nette\\DI\\Container::addService() service 'one' should be defined as 'imported'");

Assert::exception(function () use ($container, $service) {
	$container->addService('', $service);
}, Nette\InvalidArgumentException::class, 'Service name must be a non-empty string.');

Assert::exception(function () use ($container) {
	$container->addService('one', null);
}, Nette\InvalidArgumentException::class, "Service 'one' must be a object, NULL given.");

Assert::exception(function () use ($container) {
	$container->getService('one');
}, Nette\DI\MissingServiceException::class, "Service 'one' not found.");

Assert::exception(function () use ($container, $service) {
	@$container->addService('one', $service); // @ triggers service should be defined as "imported"
	$container->addService('one', $service);
}, Nette\InvalidStateException::class, "Service 'one' already exists.");
