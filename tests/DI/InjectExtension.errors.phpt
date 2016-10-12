<?php

/**
 * Test: Nette\DI\Compiler: inject.
 */

use Nette\DI;
use Nette\InvalidStateException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class ServiceA
{

	/** @var DateTimeImmutable @inject */
	public $a;
}


class ServiceB
{

	/** @var Unknown @inject */
	public $a;
}


class ServiceC
{

	/** @inject */
	public $a;
}


$compiler = new DI\Compiler;
$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);


Assert::exception(function () use ($compiler) {
	createContainer($compiler, '
services:
	service:
		class: ServiceA
		inject: on
');
}, InvalidStateException::class, 'Service of type DateTimeImmutable used in @var annotation at ServiceA::$a not found. Did you register it in configuration file?');


Assert::exception(function () use ($compiler) {
	createContainer($compiler, '
services:
	service:
		class: ServiceB
		inject: on
');
}, InvalidStateException::class, "Class or interface 'Unknown' used in @var annotation at ServiceB::\$a not found. Check annotation and 'use' statements.");


Assert::exception(function () use ($compiler) {
	createContainer($compiler, '
services:
	service:
		class: ServiceC
		inject: on
');
}, InvalidStateException::class, 'Property ServiceC::$a has no @var annotation.');
