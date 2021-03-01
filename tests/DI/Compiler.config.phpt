<?php

/**
 * Test: Nette\DI\Compiler and config.
 */

declare(strict_types=1);

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
