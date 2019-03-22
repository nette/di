<?php

/**
 * Test: Nette\DI\ContainerBuilder::getServiceDefinition()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$builder = new DI\ContainerBuilder;
$definitionOne = $builder->addDefinition('one')
	->setType(stdClass::class);

$builder->addFactoryDefinition('two')
	->getResultDefinition()
	->setFactory(SplFileInfo::class);


$definition = $builder->getServiceDefinition('one');
Assert::same($definitionOne, $definition);

Assert::exception(function () use ($builder) {
	$builder->getServiceDefinition('unknown');
}, Nette\DI\MissingServiceException::class, "Service 'unknown' not found.");

Assert::exception(function () use ($builder) {
	$builder->getServiceDefinition('two');
}, Nette\DI\MissingServiceException::class, "ServiceDefinition with name 'two' not found.");
