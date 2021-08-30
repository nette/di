<?php

/**
 * Test: FactoryDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\FactoryDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Good1
{
	public function create();
}

interface Good2
{
	public function create(): stdClass;
}


Assert::exception(function () {
	$def = new FactoryDefinition;
	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
}, Nette\DI\ServiceCreationException::class, 'Type is missing in definition of service.');


Assert::exception(function () {
	$def = new FactoryDefinition;
	$def->setImplement('Good1');
	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
}, Nette\DI\ServiceCreationException::class, 'Service of type Good1: Method create() has no return type or annotation @return.');


Assert::noError(function () {
	$def = new FactoryDefinition;
	$def->setImplement('Good1');
	$def->getResultDefinition()->setType('stdClass');

	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
	Assert::same('stdClass', $def->getResultType());
});


Assert::noError(function () {
	$def = new FactoryDefinition;
	$def->setImplement('Good2');

	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
	Assert::same('stdClass', $def->getResultType());
});


Assert::noError(function () {
	$def = new FactoryDefinition;
	$def->setImplement('Good2');

	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
	$resolver->completeDefinition($def);
});
