<?php

/**
 * Test: Nette\DI\ContainerBuilder::getAccessorDefinition()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';

interface AccessorDefinition
{
	public function get();
}

$builder = new DI\ContainerBuilder;
$definitionOne = $builder->addAccessorDefinition('one')
	->setImplement(AccessorDefinition::class)
	->setClass(\stdClass::class);

$builder->addDefinition('two')
	->setType(stdClass::class);


$definition = $builder->getAccessorDefinition('one');
Assert::same($definitionOne, $definition);

Assert::exception(function () use ($builder) {
	$builder->getAccessorDefinition('unknown');
}, Nette\DI\MissingServiceException::class, "Service 'unknown' not found.");

Assert::exception(function () use ($builder) {
	$builder->getAccessorDefinition('two');
}, Nette\DI\MissingServiceException::class, "AccessorDefinition with name 'two' not found.");
