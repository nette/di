<?php

/**
 * Test: Nette\DI\ContainerBuilder and Container: findByTag()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType(stdClass::class);
$builder->addDefinition('two')
	->setType(stdClass::class)
	->addTag('debugPanel', true);
$builder->addDefinition('three')
	->setType(stdClass::class)
	->addTag('component');
$builder->addDefinition('five')
	->setType(stdClass::class)
	->addTag('debugPanel', [1, 2, 3])
	->addTag('typeHint', 'Service');


test('compile-time', function () use ($builder) {
	Assert::same([
		'five' => 'Service',
	], $builder->findByTag('typeHint'));

	Assert::same([
		'two' => true,
		'five' => [1, 2, 3],
	], $builder->findByTag('debugPanel'));

	Assert::same([
		'three' => true,
	], $builder->findByTag('component'));

	Assert::same([], $builder->findByTag('unknown'));
});


test('run-time', function () use ($builder) {
	$container = createContainer($builder);

	Assert::same([
		'five' => 'Service',
	], $container->findByTag('typeHint'));

	Assert::same([
		'five' => [1, 2, 3],
		'two' => true,
	], $container->findByTag('debugPanel'));

	Assert::same([], $container->findByTag('unknown'));
});
