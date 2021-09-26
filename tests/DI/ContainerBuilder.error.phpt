<?php

/**
 * Test: Nette\DI\ContainerBuilder.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType(stdClass::class)
	->addSetup('::1234');

Assert::exception(function () use ($builder) {
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of stdClass): Expected function, method or property name, '1234' given.");



$builder = new DI\ContainerBuilder;
$builder->addDefinition('extension.one')
	->setType(stdClass::class);
$builder->addDefinition('25_service')
	->setType(stdClass::class);

Assert::exception(function () use ($builder) {
	$builder->getByType(stdClass::class);
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type stdClass found: 25_service, extension.one. If you want to overwrite service extension.one, give it proper name.');



$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType(stdClass::class)
	->addSetup('$prop[]');

Assert::exception(function () use ($builder) {
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of stdClass): Missing argument for \$prop[].");
