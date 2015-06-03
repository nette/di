<?php

/**
 * Test: Nette\DI\ContainerBuilder and removeDefinition.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;

$builder->addDefinition('one')
	->setClass('stdClass');

$builder->addDefinition('two')
	->setClass('stdClass');

$builder->prepareClassList();

Assert::exception(function () use ($builder) {
	$builder->getByType('stdClass');
}, 'Nette\DI\ServiceCreationException', 'Multiple services of type stdClass found: one, two');


$builder->removeDefinition('two');

Assert::same( 'one', $builder->getByType('stdClass') );
