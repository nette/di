<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
	public const Name = 'hello';

	public $arg;


	public function __construct($arg)
	{
		$this->arg = $arg;
	}
}

test('', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	services:
		one: Service(%dynamic%)
	', ['dynamic' => 123]);
	Assert::same(123, $container->getService('one')->arg);
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	services:
		one: Service(%dynamic.item%)
	', ['dynamic' => ['item' => 123]]);
	Assert::same(123, $container->getService('one')->arg);
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::exception(
		fn() => createContainer($compiler, '
		parameters:
			dynamic: default
		'),
		Nette\InvalidArgumentException::class,
		'Missing parameters: dynamic',
	);
});


test('', function () {
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


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	parameters:
		expand: hello%dynamic%
	services:
		one: Service(%expand%)
	', ['dynamic' => 123]);
	Assert::same('hello123', $container->getService('one')->arg);
});


test('', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	parameters:
		dynamic: default
		expand: %dynamic.item%

	services:
		one: Service(%expand%)
	', ['dynamic' => ['item' => 123]]);
	Assert::same(123, $container->getService('one')->arg);
});
