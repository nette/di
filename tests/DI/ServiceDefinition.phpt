<?php

/**
 * Test: ServiceDefinition
 *
 * @author     David Grudl
 */

use Nette\DI\ServiceDefinition,
	Nette\DI\Statement,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function(){
	$def = new ServiceDefinition;
	$def->setClass('Class');
	Assert::same( 'Class', $def->getClass() );
	Assert::null( $def->getFactory() );

	$def->setArguments(array(1, 2));
	Assert::same( 'Class', $def->getClass() );
	Assert::equal( new Statement('Class', array(1, 2)), $def->getFactory() );
});

test(function(){
	$def = new ServiceDefinition;
	$def->setClass('Class', array());
	Assert::same( 'Class', $def->getClass() );
	Assert::null( $def->getFactory() );
});

test(function(){
	$def = new ServiceDefinition;
	$def->setClass('Class', array(1, 2));
	Assert::same( 'Class', $def->getClass() );
	Assert::equal( new Statement('Class', array(1, 2)), $def->getFactory() );
});

test(function(){
	$def = new ServiceDefinition;
	$def->setFactory('Class');
	Assert::null( $def->getClass() );
	Assert::equal( new Statement('Class', array()), $def->getFactory() );

	$def->setArguments(array(1, 2));
	Assert::null( $def->getClass() );
	Assert::equal( new Statement('Class', array(1, 2)), $def->getFactory() );
});

test(function(){
	$def = new ServiceDefinition;
	$def->setFactory('Class', array(1, 2));
	Assert::null( $def->getClass() );
	Assert::equal( new Statement('Class', array(1, 2)), $def->getFactory() );
});

test(function(){
	$def = new ServiceDefinition;
	$def->setFactory(new Statement('Class', array(1, 2)));
	Assert::null( $def->getClass() );
	Assert::equal( new Statement('Class', array(1, 2)), $def->getFactory() );
});

test(function(){
	$def = new ServiceDefinition;
	$def->setFactory(new Statement('Class', array(1, 2)), array(99)); // 99 is ignored
	Assert::null( $def->getClass() );
	Assert::equal( new Statement('Class', array(1, 2)), $def->getFactory() );
});

test(function(){
	$def = new ServiceDefinition;
	$def->addSetup('Class', array(1, 2));
	$def->addSetup(new Statement('Class', array(1, 2)));
	$def->addSetup(new Statement('Class', array(1, 2)), array(99)); // 99 is ignored
	Assert::equal( array(
		new Statement('Class', array(1, 2)),
		new Statement('Class', array(1, 2)),
		new Statement('Class', array(1, 2)),
	), $def->getSetup() );
});

test(function(){
	$def = new ServiceDefinition;
	$def->addTag('tag1');
	$def->addTag('tag2', array(1, 2));
	Assert::equal( array(
		'tag1' => TRUE,
		'tag2' => array(1, 2),
	), $def->getTags() );
});
