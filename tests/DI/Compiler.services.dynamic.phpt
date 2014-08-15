<?php

/**
 * Test: Nette\DI\Compiler and dynamic services.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service extends Nette\Object
{
}


$container = createContainer(new DI\Compiler, '
services:
	one:
		class: Service
		dynamic: true
');


Assert::exception(function() use ($container) {
	$container->getService('one');
}, 'Nette\DI\ServiceCreationException', "Unable to create dynamic service 'one', it must be added using addService()");
