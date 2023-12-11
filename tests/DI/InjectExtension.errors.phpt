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
		factory: ServiceA
		inject: yes
');
}, InvalidStateException::class, 'Service of type DateTimeImmutable required by ServiceA::$a not found. Did you add it to configuration file?');


Assert::exception(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
	createContainer($compiler, '
services:
	service:
		factory: ServiceB
		inject: yes
');
}, InvalidStateException::class, "Class 'Unknown' not found.
Check the type of property ServiceB::\$a.");


Assert::exception(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
	createContainer($compiler, '
services:
	service:
		factory: ServiceC
		inject: yes
');
}, InvalidStateException::class, 'Type of property ServiceC::$a is not declared.');


Assert::error(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
	createContainer($compiler, '
services:
	service:
		factory: ServiceD
		inject: yes
');
}, E_USER_WARNING, 'Property ServiceD::$a for injection must be public and non-static.');


Assert::error(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
	createContainer($compiler, '
services:
	service:
		factory: ServiceE
		inject: yes
');
}, E_USER_WARNING, 'Property ServiceE::$a for injection must be public and non-static.');
