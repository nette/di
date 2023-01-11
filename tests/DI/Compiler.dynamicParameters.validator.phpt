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

test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::exception(function () use ($compiler) {
		createContainer($compiler, '
		foo:
			key:
				string: %dynamic%
		', ['dynamic' => 123]);
	}, Nette\Utils\AssertionException::class, 'The dynamic parameter expects to be string, int 123 given.');
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::exception(function () use ($compiler) {
		createContainer($compiler, '
		foo:
			key:
				string: %dynamic%
		', ['dynamic' => null]);
	}, Nette\Utils\AssertionException::class, 'The dynamic parameter expects to be string, null given.');
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::exception(function () use ($compiler) {
		createContainer($compiler, '
		foo:
			key:
				string: %dynamic.sub%
		', ['dynamic' => ['sub' => 123]]);
	}, Nette\Utils\AssertionException::class, 'The dynamic parameter expects to be string, int 123 given.');
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::noError(function () use ($compiler) {
		createContainer($compiler, '
		foo:
			key:
				intnull: %dynamic%
		', ['dynamic' => 123]);
	});
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('foo', new FooExtension);
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::noError(function () use ($compiler) {
		createContainer($compiler, '
		foo:
			key:
				intnull: %dynamic%
		', ['dynamic' => null]);
	});
});
