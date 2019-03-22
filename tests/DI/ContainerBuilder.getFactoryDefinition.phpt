<?php

/**
 * Test: Nette\DI\ContainerBuilder::getFactoryDefinition()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$builder = new DI\ContainerBuilder;
$definitionOne = $builder->addFactoryDefinition('one');
$definitionOne->getResultDefinition()
	->setFactory(SplFileInfo::class);

$builder->addDefinition('two')
	->setType(stdClass::class);


$definition = $builder->getFactoryDefinition('one');
Assert::same($definitionOne, $definition);

Assert::exception(function () use ($builder) {
	$builder->getFactoryDefinition('unknown');
}, Nette\DI\MissingServiceException::class, "Service 'unknown' not found.");

Assert::exception(function () use ($builder) {
	$builder->getFactoryDefinition('two');
}, Nette\DI\MissingServiceException::class, "FactoryDefinition with name 'two' not found.");
