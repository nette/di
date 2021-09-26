<?php

/**
 * Test: ImportedDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\ImportedDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$def = new ImportedDefinition;
	$def->setType('Foo');
}, Nette\InvalidArgumentException::class, "Service '': Class or interface 'Foo' not found.");


Assert::exception(function () {
	$def = new ImportedDefinition;
	$resolver = new Nette\DI\Resolver(new Nette\DI\ContainerBuilder);
	$resolver->resolveDefinition($def);
}, Nette\DI\ServiceCreationException::class, 'Type of service is unknown.');


test('', function () {
	$def = new ImportedDefinition;
	$def->setName('abc');
	$def->setType(stdClass::class);

	$builder = new Nette\DI\ContainerBuilder;
	$resolver = new Nette\DI\Resolver($builder);

	$resolver->resolveDefinition($def);
	$resolver->completeDefinition($def);

	$phpGenerator = new Nette\DI\PhpGenerator($builder);
	$method = $phpGenerator->generateMethod($def);

	Assert::match(
		<<<'XX'
public function createServiceAbc(): void
{
	throw new Nette\DI\ServiceCreationException('Unable to create imported service \'abc\', it must be added using addService()');
}
XX
,
		$method->__toString()
	);
});
