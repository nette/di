<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler and config.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

test('Compiler config', function () {
	$compiler = new DI\Compiler;

	Assert::same(
		[],
		$compiler->getConfig(),
	);

	$compiler->addConfig([
		'parameters' => [
			'item1' => 1,
		],
	]);
	$compiler->compile();

	Assert::same(
		[
			'parameters' => [
				'item1' => 1,
			],
			'services' => [],
		],
		$compiler->getConfig(),
	);
});

test('Compiler config with parameters overriding', function () {
	$compiler = new DI\Compiler;

	$compiler->loadConfig(Tester\FileMock::create('
parameters:
	languages: [java]
', 'neon'));

	$compiler->loadConfig(Tester\FileMock::create('
parameters:
	languages!: [php,node]
', 'neon'));

	$compiler->compile();

	Assert::same(
		['php', 'node'],
		$compiler->getConfig()['parameters']['languages'],
	);
});

test('Compiler config with parameters overriding by a parameter', function () {
	$compiler = new DI\Compiler;

	$compiler->loadConfig(Tester\FileMock::create('
parameters:
	env: [php, node]
	languages: [java]
	version: [1]
', 'neon'));

	$compiler->loadConfig(Tester\FileMock::create('
parameters:
	languages!: %env%
	version!: 2
', 'neon'));

	$compiler->compile();

	Assert::same(['php', 'node'], $compiler->getContainerBuilder()->parameters['languages']);
	Assert::same(2, $compiler->getContainerBuilder()->parameters['version']);
});


test('Compiler config with whole section overriding', function () {
	$compiler = new DI\Compiler;

	$compiler->loadConfig(Tester\FileMock::create('
parameters:
	languages: [java]
	debug: true
', 'neon'));

	$compiler->loadConfig(Tester\FileMock::create('
parameters!:
	languages: [php]
', 'neon'));

	$compiler->compile();

	Assert::same(
		['languages' => ['php']],
		$compiler->getConfig()['parameters'],
	);
});


test('replacing an item of an anonymous service leaves the anonymous services of earlier layers alone', function () {
	$compiler = new DI\Compiler;

	$compiler->loadConfig(Tester\FileMock::create('
services:
	- {create: ArrayObject, setup: [setFlags(1)]}
', 'neon'));

	$compiler->loadConfig(Tester\FileMock::create('
services:
	- {create: ArrayIterator, setup!: [setFlags(2)]}
', 'neon'));

	$compiler->compile();
	$builder = $compiler->getContainerBuilder();

	Assert::count(1, $builder->getDefinitionByType(ArrayObject::class)->getSetup());
	Assert::count(1, $builder->getDefinitionByType(ArrayIterator::class)->getSetup());
});


test('service replaced by a definition that is not an array', function () {
	$compiler = new DI\Compiler;

	$compiler->loadConfig(Tester\FileMock::create('
services:
	foo:
		create: ArrayObject
		setup: [setFlags(1)]
		tags: [a]
', 'neon'));

	$compiler->loadConfig(Tester\FileMock::create('
services:
	foo!: ArrayIterator
', 'neon'));

	$compiler->compile();
	$def = $compiler->getContainerBuilder()->getDefinition('foo');

	Assert::same(ArrayIterator::class, $def->getType());
	Assert::same([], $def->getSetup());
	Assert::same([], $def->getTags());
});


test('service defined in code replaced by a definition that is not an array', function () {
	$compiler = new DI\Compiler;
	$compiler->getContainerBuilder()->addDefinition('foo')
		->setCreator(ArrayObject::class)
		->addSetup('setFlags', [1]);

	$compiler->loadConfig(Tester\FileMock::create('
services:
	foo!: ArrayIterator([])
', 'neon'));

	$compiler->compile();
	$def = $compiler->getContainerBuilder()->getDefinition('foo');

	Assert::same(ArrayIterator::class, $def->getType());
	Assert::same([], $def->getSetup());
});


test('service arguments replaced by a parameter', function () {
	$compiler = new DI\Compiler;

	$compiler->loadConfig(Tester\FileMock::create('
parameters:
	args: [[b]]
services:
	foo:
		create: ArrayObject
		arguments: [[a]]
', 'neon'));

	$compiler->loadConfig(Tester\FileMock::create('
services:
	foo:
		arguments!: %args%
', 'neon'));

	$compiler->compile();

	Assert::same([['b']], $compiler->getContainerBuilder()->getDefinition('foo')->getCreator()->arguments);
});
