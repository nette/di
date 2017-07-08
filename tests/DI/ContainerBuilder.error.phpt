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
	->setClass('stdClass')
	->addSetup('::1234');

Assert::exception(function () use ($builder) {
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Expected function, method or property name, '1234' given.");



$builder = new DI\ContainerBuilder;
$builder->addDefinition('extension.one')
	->setClass('stdClass');
$builder->addDefinition('25_service')
	->setClass('stdClass');

Assert::exception(function () use ($builder) {
	$builder->getByType(stdClass::class);
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type stdClass found: extension.one, 25_service. If you want to overwrite service extension.one, give it proper name.');



$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setClass('stdClass')
	->addSetup('$prop[]');

Assert::exception(function () use ($builder) {
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one': Missing argument for \$prop[].");
