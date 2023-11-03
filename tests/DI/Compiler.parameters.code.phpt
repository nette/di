<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Service
{
	public function __construct()
	{
	}
}


$loader = new Nette\DI\Config\Loader;
$config = $loader->load(Tester\FileMock::create('
parameters:
	static: 123
	expr: ::trim(" a ")
	dynamic: 123
	dynamicArray:
		dynamic: %dynamic%
		inner: %arrayDynamic.dynamic.foo%
		expr: ::trim(" a ")
	arrayExpr:
		expr: ::trim(" a ")
	arrayExpr2:
		expr: %expr%
	arrayDynamic:
		dynamic: %dynamic%
		inner: %arrayDynamic.dynamic.foo%
	arrayMix:
		expr: %expr%
		dynamic: %dynamic%
	refStatic: %static%
	refDynamic: %dynamic%
	refDynamic2: %dynamic.foo%
	refExpr: %expr%
	refArrayE1: %arrayExpr%
	refArrayE2: %arrayExpr.expr%
	refArrayD1: %arrayDynamic%
	refArrayD2: %arrayDynamic.dynamic%
	refArrayD3: %refArrayD2.foo%

services:
	- Service(
		%static%
		%expr%
		%dynamic%
		%dynamic.foo%
		%arrayExpr%
		%arrayExpr.expr%
		%arrayDynamic%
		%arrayDynamic.dynamic%
		%arrayDynamic.inner%
	)
', 'neon'));

$compiler = new Nette\DI\Compiler;
$compiler->setDynamicParameterNames(['dynamic', 'dynamicArray']);
$code = $compiler->addConfig($config)
	->compile();

Assert::matchFile(
	__DIR__ . '/expected/compiler.parameters.php',
	$code
);
