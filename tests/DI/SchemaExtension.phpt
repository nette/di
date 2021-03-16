<?php

/**
 * Test: SchemaExtension.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Extensions\SchemaExtension;
use Nette\Schema\Expect;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$voidExtension = new class extends DI\CompilerExtension {
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::mixed();
	}


	public function setConfig($config): void
	{
		// Nothing
	}
};

$loader = new DI\Config\Loader;

$createCompiler = static function () use ($voidExtension, $loader) {
	$compiler = new DI\Compiler;
	$compiler->addExtension('schema', new SchemaExtension);

	$rootKeys = ['string', 'string2', 'structure', 'notValidatedValue'];
	foreach ($rootKeys as $key) {
		$compiler->addExtension($key, $voidExtension);
	}

	$schemaConfig = $loader->load(Tester\FileMock::create(/** @lang neon */ '
schema:
	string: string()
	structure: structure([
		string: string(),
		stringWithDefault: schema(string("default value"), required(false))
		int: int(),
		float: float(),
		bool: bool(),
		array: arrayOf(string())
		list: listOf(string())
		type: type("string|int")
		schema1: schema(string())
		schema2: schema(string(), nullable())
		schema3: schema(string(), nullable(), required(false))
		schema4: schema(int(), min(10), max(20))
	])
', 'neon'));
	$compiler->addConfig($schemaConfig);

	$validConfig = $loader->load(Tester\FileMock::create(/** @lang neon */ '
string: string
structure:
	string: text
	int: 123
	float: 123.456
	bool: true
	array: [key: string, anotherString]
	list: [string, anotherString]
	type: string
	schema1: string
	schema2: null
	#schema3 is not required
	schema4: 15
notValidatedValue: literally anything
', 'neon'));
	$compiler->addConfig($validConfig);

	return $compiler;
};

test('no error', static function () use ($createCompiler) {
	$compiler = $createCompiler();

	Assert::noError(static function () use ($compiler) {
		eval($compiler->compile());
	});
});

test('all values are required by default', static function () use ($createCompiler, $loader) {
	$compiler = $createCompiler();

	$config = $loader->load(Tester\FileMock::create(/** @lang neon */ '
schema:
	string2: string()

string2: false 
', 'neon'));

	Assert::exception(static function () use ($compiler, $config) {
		eval($compiler->addConfig($config)->compile());
	}, DI\InvalidConfigurationException::class, "The item 'string2' expects to be string, false given.");
});

test('invalid type', static function () use ($createCompiler, $loader) {
	$compiler = $createCompiler();

	$config = $loader->load(Tester\FileMock::create(/** @lang neon */ '
structure:
	string: false
', 'neon'));

	Assert::exception(static function () use ($compiler, $config) {
		eval($compiler->addConfig($config)->compile());
	}, DI\InvalidConfigurationException::class, "The item 'structure › string' expects to be string, false given.");
});

test('invalid type argument', static function () use ($createCompiler, $loader) {
	$compiler = $createCompiler();

	$config = $loader->load(Tester\FileMock::create(/** @lang neon */ '
structure:
	list: [arr: ayy!]
', 'neon'));

	Assert::exception(static function () use ($compiler, $config) {
		eval($compiler->addConfig($config)->compile());
	}, DI\InvalidConfigurationException::class, "The item 'structure › list' expects to be list, array given.");
});

test('invalid type argument 2', static function () use ($createCompiler, $loader) {
	$compiler = $createCompiler();

	$config = $loader->load(Tester\FileMock::create(/** @lang neon */ '
structure:
	schema4: 21
', 'neon'));

	Assert::exception(static function () use ($compiler, $config) {
		eval($compiler->addConfig($config)->compile());
	}, DI\InvalidConfigurationException::class, "The item 'structure › schema4' expects to be in range 10..20, 21 given.");
});

test('empty schema()', static function () use ($createCompiler, $loader) {
	$compiler = $createCompiler();

	$config = $loader->load(Tester\FileMock::create(/** @lang neon */ '
schema:
	emptySchema: schema()
', 'neon'));

	Assert::exception(static function () use ($compiler, $config) {
		eval($compiler->addConfig($config)->compile());
	}, Nette\InvalidArgumentException::class, 'schema() should have at least one argument.');
});

test('invalid schema() argument', static function () use ($createCompiler, $loader) {
	$compiler = $createCompiler();

	$config = $loader->load(Tester\FileMock::create(/** @lang neon */ '
schema:
	emptySchema: schema(123)
', 'neon'));

	Assert::exception(static function () use ($compiler, $config) {
		eval($compiler->addConfig($config)->compile());
	}, Nette\InvalidArgumentException::class, 'schema() should contain another statement(), integer given.');
});
