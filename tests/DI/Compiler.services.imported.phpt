<?php

/**
 * Test: Nette\DI\Compiler and dynamic services.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
}


$container = createContainer(new DI\Compiler, '
services:
	one:
		type: Service
		imported: true
');

Assert::exception(function () use ($container) {
	$container->getService('one');
}, Nette\DI\ServiceCreationException::class, "Unable to create imported service 'one', it must be added using addService()");
