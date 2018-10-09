<?php

/**
 * Test: FactoryDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\FactoryDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$def = new FactoryDefinition;
	$def->setType('Foo');
}, Nette\MemberAccessException::class);

Assert::exception(function () {
	$def = new FactoryDefinition;
	$def->setImplement('Foo');
}, Nette\InvalidArgumentException::class, "Service '': Interface 'Foo' not found.");

Assert::exception(function () {
	$def = new FactoryDefinition;
	$def->setImplement('stdClass');
}, Nette\InvalidArgumentException::class, "Service '': Interface 'stdClass' not found.");


interface Bad1
{
	public static function create();
}

Assert::exception(function () {
	(new Nette\DI\Definitions\FactoryDefinition)
		->setImplement('Bad1');
}, Nette\InvalidArgumentException::class, 'Interface Bad1 must have just one non-static method create() or get().');


interface Bad2
{
	public function createx();
}

Assert::exception(function () {
	(new Nette\DI\Definitions\FactoryDefinition)
		->setImplement('Bad2');
}, Nette\InvalidArgumentException::class, 'Interface Bad2 must have just one non-static method create() or get().');


interface Bad3
{
	public function other();

	public function create();
}

Assert::exception(function () {
	(new Nette\DI\Definitions\FactoryDefinition)
		->setImplement('Bad3');
}, Nette\InvalidArgumentException::class, 'Interface Bad3 must have just one non-static method create() or get().');
