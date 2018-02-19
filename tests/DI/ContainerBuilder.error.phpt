<?php

/**
 * Test: Nette\DI\ContainerBuilder.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType('stdClass')
	->addSetup('::1234');

Assert::exception(function () use ($builder) {
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of stdClass): Expected function, method or property name, '1234' given.");



$builder = new DI\ContainerBuilder;
$builder->addDefinition('extension.one')
	->setType('stdClass');
$builder->addDefinition('25_service')
	->setType('stdClass');

Assert::exception(function () use ($builder) {
	$builder->getByType(stdClass::class);
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type stdClass found: extension.one, 25_service. If you want to overwrite service extension.one, give it proper name.');



$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType('stdClass')
	->addSetup('$prop[]');

Assert::exception(function () use ($builder) {
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of stdClass): Missing argument for \$prop[].");
