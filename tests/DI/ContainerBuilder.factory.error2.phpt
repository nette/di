<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated factories errors.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


trait Bad1
{
	function method()
	{
	}
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Bad1::method');
	$builder->complete();
}, Nette\InvalidStateException::class, "Method Bad1::method() used in service 'one' is not callable.");
