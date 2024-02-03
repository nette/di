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
	->setType(stdClass::class);

$builder->addDefinition('two')
	->setType(SplFileInfo::class);

$builder->addDefinition('three')
	->setType(SplFileInfo::class);


$definition = $builder->getDefinitionByType(stdClass::class);
Assert::same($definitionOne, $definition);

Assert::exception(
	fn() => $builder->getDefinitionByType('unknown'),
	Nette\DI\MissingServiceException::class,
	"Service of type 'unknown' not found. Check the class name because it cannot be found.",
);

Assert::exception(
	fn() => $builder->getDefinitionByType(SplFileInfo::class),
	Nette\DI\ServiceCreationException::class,
	'Multiple services of type SplFileInfo found: three, two',
);
