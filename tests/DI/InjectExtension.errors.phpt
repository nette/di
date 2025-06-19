<?php

/**
 * Test: Nette\DI\Compiler: inject.
 */

declare(strict_types=1);

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


class ServiceD
{
	/** @inject */
	protected $a;
}


class ServiceE
{
	/** @inject */
	public static $a;
}


Assert::exception(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
	createContainer($compiler, '
services:
	service:
		create: ServiceA
		inject: yes
');
}, InvalidStateException::class, "[Service 'service' of type ServiceA]
Service of type DateTimeImmutable required by ServiceA::\$a not found.
Did you add it to configuration file?");


Assert::exception(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
	createContainer($compiler, '
services:
	service:
		create: ServiceB
		inject: yes
');
}, InvalidStateException::class, "Class 'Unknown' not found.
Check the type of property ServiceB::\$a.");
// }, InvalidStateException::class, "[Service 'service' of type ServiceB]
// Class 'Unknown' required by ServiceB::\$a not found.
// Check the property type and 'use' statements.");


Assert::exception(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
	createContainer($compiler, '
services:
	service:
		create: ServiceC
		inject: yes
');
}, InvalidStateException::class, 'Type of property ServiceC::$a is not declared.');
//}, InvalidStateException::class, "[Service 'service' of type ServiceC]
//Property ServiceC::\$a has no type.");


Assert::exception(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
	createContainer($compiler, '
services:
	service:
		create: ServiceD
		inject: yes
');
}, InvalidStateException::class, 'Property ServiceD::$a for injection must not be static, readonly and must be public.');


Assert::exception(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
	createContainer($compiler, '
services:
	service:
		create: ServiceE
		inject: yes
');
}, InvalidStateException::class, 'Property ServiceE::$a for injection must not be static, readonly and must be public.');
