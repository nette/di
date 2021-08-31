<?php

/**
 * Test: Nette\DI\ContainerBuilder.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType('stdClass')
	->addSetup('::1234');

Assert::exception(function () use ($builder) {
	$builder->complete();
}, Nette\InvalidStateException::class, "[Service 'one' of type stdClass]
Expected function, method or property name, '1234' given.");



$builder = new DI\ContainerBuilder;
$builder->addDefinition('extension.one')
	->setType('stdClass');
$builder->addDefinition('25_service')
	->setType('stdClass');

Assert::exception(function () use ($builder) {
	$builder->getByType(stdClass::class);
}, Nette\DI\ServiceCreationException::class, 'Multiple services of type stdClass found: 25_service, extension.one. If you want to overwrite service extension.one, give it proper name.');



$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType('stdClass')
	->addSetup('$prop[]');

Assert::exception(function () use ($builder) {
	$builder->complete();
}, Nette\InvalidStateException::class, "[Service 'one' of type stdClass]
Missing argument for \$prop[].");



$builder = new DI\ContainerBuilder;
$builder->addDefinition(null)
	->setFactory([new Statement('Unknown'), 'foo']);

Assert::exception(function () use ($builder) {
	$builder->complete();
}, Nette\DI\ServiceCreationException::class, "[Service Unknown()::foo()]
Class 'Unknown' not found.");



$builder = new DI\ContainerBuilder;
$builder->addDefinition(null)
	->setFactory('stdClass')
	->addSetup([new Statement('Unknown'), 'foo']);

Assert::exception(function () use ($builder) {
	$builder->complete();
}, Nette\DI\ServiceCreationException::class, "[Service of type stdClass]
Class 'Unknown' not found.");
