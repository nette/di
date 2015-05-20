<?php

/**
 * Test: Nette\DI\ContainerBuilder and Container: findByTag()
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setClass('stdClass');
$builder->addDefinition('two')
	->setClass('stdClass')
	->addTag('debugPanel', TRUE);
$builder->addDefinition('three')
	->setClass('stdClass')
	->addTag('component');
$builder->addDefinition('five')
	->setClass('stdClass')
	->addTag('debugPanel', [1, 2, 3])
	->addTag('typeHint', 'Service');


test(function() use ($builder) { // compile-time
	Assert::same( [
		'five' => 'Service',
	], $builder->findByTag('typeHint') );

	Assert::same( [
		'two' => TRUE,
		'five' => [1, 2, 3],
	], $builder->findByTag('debugPanel') );

	Assert::same( [
		'three' => TRUE,
	], $builder->findByTag('component') );

	Assert::same( [], $builder->findByTag('unknown') );
});


test(function() use ($builder) { // run-time
	$container = createContainer($builder);

	Assert::same( [
		'five' => 'Service',
	], $container->findByTag('typeHint') );

	Assert::same( [
		'five' => [1, 2, 3],
		'two' => TRUE,
	], $container->findByTag('debugPanel') );

	Assert::same( [], $container->findByTag('unknown') );
});
