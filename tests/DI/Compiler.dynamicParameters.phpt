<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
	public $arg;


	public function __construct($arg)
	{
		$this->arg = $arg;
	}
}

test(function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	services:
		one: Service(%dynamic%)
	', ['dynamic' => 123]);
	Assert::same(123, $container->getService('one')->arg);
});


test(function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	services:
		one: Service(%dynamic.item%)
	', ['dynamic' => ['item' => 123]]);
	Assert::same(123, $container->getService('one')->arg);
});


test(function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	parameters:
		dynamic: default

	services:
		one: Service(%dynamic%)
	');
	Assert::same('default', $container->getService('one')->arg);
});


test(function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	parameters:
		dynamic: default

	services:
		one: Service(%dynamic%)
	', ['dynamic' => 'overwritten']);
	Assert::same('overwritten', $container->getService('one')->arg);
});


test(function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	parameters:
		expand: hello%dynamic%
	', ['dynamic' => 123]);
	Assert::same(['dynamic' => 123, 'expand' => 'hello123'], $container->parameters);
});


test(function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	parameters:
		dynamic: default
		expand: %dynamic.item%

	', ['dynamic' => ['item' => 123]]);
	Assert::same(123, $container->parameters['expand']);
});
