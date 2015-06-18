<?php

/**
 * Test: Nette\DI\ContainerBuilder.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setClass('stdClass')
	->addSetup('::1234');

Assert::exception(function () use ($builder) {
	$builder->generateClasses();
}, 'Nette\InvalidStateException', "Service 'one': Expected function, method or property name, '1234' given.");



$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setClass('stdclass');

Assert::exception(function () use ($builder) {
	$builder->generateClasses();
}, 'Nette\InvalidStateException', "Case mismatch on class name 'stdclass', correct name is 'stdClass'.");
