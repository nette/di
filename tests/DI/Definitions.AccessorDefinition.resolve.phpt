<?php

/**
 * Test: AccessorDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\AccessorDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Bad1
{
	public function get();
}

interface Good1
{
	public function get(): stdClass;
}


Assert::exception(function () {
	$def = new AccessorDefinition;
	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
}, Nette\DI\ServiceCreationException::class, 'Type of service is unknown.');


Assert::exception(function () {
	$def = new AccessorDefinition;
	$def->setImplement(Bad1::class);
}, Nette\DI\ServiceCreationException::class, 'Return type of Bad1::get() is not declared.');


Assert::noError(function () {
	$def = new AccessorDefinition;
	$def->setImplement(Good1::class);
	$def->setReference(stdClass::class);

	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
});


Assert::noError(function () {
	$def = new AccessorDefinition;
	$def->setImplement(Good1::class);

	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
});


Assert::exception(function () {
	$def = new AccessorDefinition;
	$def->setImplement(Good1::class);

	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
	$resolver->completeDefinition($def);
}, Nette\DI\ServiceCreationException::class, 'Service of type Good1: Service of type stdClass not found. Did you add it to configuration file?');
