<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated factories errors.
 * @phpversion 5.4
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


trait Bad1
{
	function method() {}
}

Assert::exception(function() {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setFactory('Bad1::method');
	$builder->generateClasses();
}, 'Nette\InvalidStateException', "Factory 'Bad1::method' used in service 'one' is not callable.");
