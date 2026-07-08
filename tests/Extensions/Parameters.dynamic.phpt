<?php declare(strict_types=1);

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


test('Nested dynamic parameter via dotted name', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['db.password']);
	$container = createContainer($compiler, '
	parameters:
		db:
			host: localhost
			password: default

	services:
		one: Service(%db.password%)
	', ['db.password' => 'secret']);
	Assert::same('secret', $container->getService('one')->arg);
	Assert::same(['host' => 'localhost', 'password' => 'secret'], $container->getParameter('db'));
});


test('Nested dynamic parameter falls back to config value', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['db.password']);
	$container = createContainer($compiler, '
	parameters:
		db:
			host: localhost
			password: default
	');
	Assert::same(['host' => 'localhost', 'password' => 'default'], $container->getParameter('db'));

	// the same class accepts a runtime value; the sibling stays baked
	$class = $container::class;
	$other = new $class(['db.password' => 'runtime']);
	Assert::same(['host' => 'localhost', 'password' => 'runtime'], $other->getParameter('db'));
});


test('Nested dynamic parameter without config value', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['db.password']);
	$container = createContainer($compiler, '
	parameters:
		db:
			host: localhost
	', ['db.password' => 'secret']);
	Assert::same(['host' => 'localhost', 'password' => 'secret'], $container->getParameter('db'));
});


test('Nested dynamic parameter within string expansion', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['db.password']);
	$container = createContainer($compiler, '
	parameters:
		db:
			password: default
		dsn: "pw=%db.password%"

	services:
		one: Service(%dsn%)
	', ['db.password' => 'secret']);
	Assert::same('pw=secret', $container->getService('one')->arg);
});


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
