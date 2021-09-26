<?php

/**
 * Test: Nette\DI\Compiler: including files
 */

declare(strict_types=1);

use Nette\DI\Compiler;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$compiler = new Compiler;
$compiler->loadConfig('files/loader.includes.neon');
$compiler->compile();

Assert::same([
	'files/loader.includes.neon',
	'files/loader.includes.child.neon',
	'files/loader.includes.child.php',
	__DIR__ . DIRECTORY_SEPARATOR . 'files/loader.includes.grandchild.neon',
	(new ReflectionClass(Nette\DI\Extensions\ServicesExtension::class))->getFileName(),
	(new ReflectionClass(Nette\DI\Extensions\ParametersExtension::class))->getFileName(),
], array_keys($compiler->exportDependencies()[1]));


Assert::equal([
	'parameters' => [
		'me' => [
			'loader.includes.child.neon',
			'loader.includes.grandchild.neon',
			'loader.includes.child.php',
		],
		'scalar' => 1,
		'list' => [5, 6, 1, 2],
		'force' => [1, 2],
	],
	'services' => [
		'a' => (object) [
			'type' => null,
			'factory' => stdClass::class,
			'arguments' => [],
			'setup' => [],
			'inject' => null,
			'autowired' => false,
			'tags' => [],
			'reset' => [],
			'alteration' => null,
			'defType' => Nette\DI\Definitions\ServiceDefinition::class,
		],
	],
], $compiler->getConfig());
