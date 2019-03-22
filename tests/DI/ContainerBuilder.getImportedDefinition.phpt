<?php

/**
 * Test: Nette\DI\ContainerBuilder::getImportedDefinition()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$builder = new DI\ContainerBuilder;
$definitionOne = $builder->addImportedDefinition('one');

$builder->addDefinition('two')
	->setType(stdClass::class);


$definition = $builder->getImportedDefinition('one');
Assert::same($definitionOne, $definition);

Assert::exception(function () use ($builder) {
	$builder->getImportedDefinition('unknown');
}, Nette\DI\MissingServiceException::class, "Service 'unknown' not found.");

Assert::exception(function () use ($builder) {
	$builder->getImportedDefinition('two');
}, Nette\DI\MissingServiceException::class, "ImportedDefinition with name 'two' not found.");
