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


$compiler = new DI\Compiler;
$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);


Assert::exception(function () use ($compiler) {
	createContainer($compiler, '
services:
	service:
		factory: ServiceA
		inject: yes
');
}, InvalidStateException::class, 'Service of type DateTimeImmutable used in type hint at ServiceA::$a not found. Did you add it to configuration file?');


Assert::exception(function () use ($compiler) {
	createContainer($compiler, '
services:
	service:
		factory: ServiceB
		inject: yes
');
}, InvalidStateException::class, "Class or interface 'Unknown' used in type hint at ServiceB::\$a not found. Check type and 'use' statements.");


Assert::exception(function () use ($compiler) {
	createContainer($compiler, '
services:
	service:
		factory: ServiceC
		inject: yes
');
}, InvalidStateException::class, 'Property ServiceC::$a has no type hint.');
