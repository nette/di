<?php

/**
 * Test: Nette\DI\ContainerBuilder::getLocatorDefinition()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$builder = new DI\ContainerBuilder;
$definitionOne = $builder->addLocatorDefinition('one');

$builder->addDefinition('two')
	->setType(stdClass::class);


$definition = $builder->getLocatorDefinition('one');
Assert::same($definitionOne, $definition);

Assert::exception(function () use ($builder) {
	$builder->getLocatorDefinition('unknown');
}, Nette\DI\MissingServiceException::class, "Service 'unknown' not found.");

Assert::exception(function () use ($builder) {
	$builder->getLocatorDefinition('two');
}, Nette\DI\MissingServiceException::class, "LocatorDefinition with name 'two' not found.");
