<?php

declare(strict_types=1);

use Nette\DI;
use Nette\Schema\Expect;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class FooExtension extends Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::arrayOf(
			Expect::structure([
				'string' => Expect::string()->dynamic(),
				'intnull' => Expect::int()->nullable()->dynamic(),
			]),
		);
	}
}

test("Dynamic parameter of type int given to 'string' configuration", function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::exception(function () use ($compiler) {
		$container = createContainer($compiler, '
		foo:
			key:
				string: %dynamic%
		', ['dynamic' => 123]);
		$container->initialize();
	}, Nette\Utils\AssertionException::class, "The dynamic parameter used in 'foo › key › string' expects to be string, int 123 given.");
});


test("Dynamic parameter of type null given to 'string' configuration", function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::exception(function () use ($compiler) {
		$container = createContainer($compiler, '
		foo:
			key:
				string: %dynamic%
		', ['dynamic' => null]);
		$container->initialize();
	}, Nette\Utils\AssertionException::class, "The dynamic parameter used in 'foo › key › string' expects to be string, null given.");
});


test("Dynamic sub-parameter of type int given to 'string' configuration", function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::exception(function () use ($compiler) {
		$container = createContainer($compiler, '
		foo:
			key:
				string: %dynamic.sub%
		', ['dynamic' => ['sub' => 123]]);
		$container->initialize();
	}, Nette\Utils\AssertionException::class, "The dynamic parameter used in 'foo › key › string' expects to be string, int 123 given.");
});


test("Dynamic parameter of type int successfully given to 'int|null' configuration", function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::noError(function () use ($compiler) {
		$container = createContainer($compiler, '
		foo:
			key:
				intnull: %dynamic%
		', ['dynamic' => 123]);
		$container->initialize();
	});
});


test("Dynamic parameter of type null successfully given to 'int|null' configuration", function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::noError(function () use ($compiler) {
		$container = createContainer($compiler, '
		foo:
			key:
				intnull: %dynamic%
		', ['dynamic' => null]);
		$container->initialize();
	});
});
