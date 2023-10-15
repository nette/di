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

test('Dynamic parameter as scalar value', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	services:
		one: Service(%dynamic%)
	', ['dynamic' => 123]);
	Assert::same(123, $container->getService('one')->arg);
});


test('Dynamic parameter as array item', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	services:
		one: Service(%dynamic.item%)
	', ['dynamic' => ['item' => 123]]);
	Assert::same(123, $container->getService('one')->arg);
});


test('Default value', function () {
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


test('Overwriting default parameter', function () {
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


test('Dynamic parameter within string expansion', function () {
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


test('Array item as dynamic parameter within string expansion', function () {
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


test('Statement as parameter', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	parameters:
		dynamic: ::trim(" a ")

	services:
		one: Service(%dynamic%)
	');
	Assert::same('a', $container->getService('one')->arg);
});


test('Class constant as parameter', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	parameters:
		dynamic: Service::Name

	services:
		one: Service(%dynamic%)
	');
	Assert::same('hello', $container->getService('one')->arg);
});


testException('Reference as parameter', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	$container = createContainer($compiler, '
	parameters:
		dynamic: @one

	services:
		one: Service(%dynamic%)
	');
	$container->getService('one');
}, Nette\InvalidStateException::class, 'Circular reference detected for: one, %dynamic%.');


testException('Circula references', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['one', 'two']);
	$container = createContainer($compiler, '
	parameters:
		one: %two%
		two: %one%
	');
	$container->getParameter('one');
}, Nette\InvalidStateException::class, 'Circular reference detected for: %one%, %two%.');
