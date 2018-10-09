<?php

/**
 * Test: ExternalDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\ExternalDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$def = new ExternalDefinition;
	$def->setType('Foo');
}, Nette\InvalidArgumentException::class, "Service '': Class or interface 'Foo' not found.");


Assert::exception(function () {
	$def = new ExternalDefinition;
	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
}, Nette\DI\ServiceCreationException::class, "Service '': Type of service is unknown.");


test(function () {
	$def = new ExternalDefinition;
	$def->setName('abc');
	$def->setType('stdClass');

	$builder = new Nette\DI\ContainerBuilder;
	$resolver = new Nette\DI\Resolver($builder);

	$resolver->resolveDefinition($def);
	$resolver->completeDefinition($def);

	$phpGenerator = new Nette\DI\PhpGenerator($builder);
	$method = $phpGenerator->generateMethod($def);

	Assert::match(
'public function createServiceAbc(): void
{
	throw new Nette\DI\ServiceCreationException(\'Unable to create external service \\\'abc\\\', it must be added using addService()\');
}', $method->__toString());
});
