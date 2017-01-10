<?php

/**
 * Test: Nette\DI\ContainerBuilder::getDefinitionByType()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

$builder = new DI\ContainerBuilder;
$definitionOne = $builder->addDefinition('one')
	->setClass(stdClass::class);

$builder->addDefinition('two')
	->setClass(SplFileInfo::class);

$builder->addDefinition('three')
	->setClass(SplFileInfo::class);


$definition = $builder->getDefinitionByType(stdClass::class);
Assert::same($definitionOne, $definition);

Assert::exception(function () use ($builder) {
	$builder->getDefinitionByType('unknown');
}, Nette\DI\MissingServiceException::class, "Service of type 'unknown' not found.");

Assert::exception(function () use ($builder) {
	$builder->getDefinitionByType(SplFileInfo::class);
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type SplFileInfo found: two, three');
