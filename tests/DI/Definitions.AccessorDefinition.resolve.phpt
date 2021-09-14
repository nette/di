<?php

/**
 * Test: AccessorDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\AccessorDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Good1
{
	public function get();
}

interface Good2
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
	$def->setImplement('Good1');
	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
	$resolver->completeDefinition($def);
}, Nette\DI\ServiceCreationException::class, 'Service of type Good1: Return type of get() is not declared.');


Assert::noError(function () {
	$def = new AccessorDefinition;
	$def->setImplement('Good1');
	$def->setReference('stdClass');

	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
});


Assert::noError(function () {
	$def = new AccessorDefinition;
	$def->setImplement('Good2');

	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
});


Assert::exception(function () {
	$def = new AccessorDefinition;
	$def->setImplement('Good2');

	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
	$resolver->completeDefinition($def);
}, Nette\DI\ServiceCreationException::class, 'Service of type Good2: Service of type stdClass not found. Did you add it to configuration file?');
