<?php declare(strict_types=1);

use Nette\DI;
use Nette\DI\DynamicValue;
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

test('Top-level DynamicValue with default', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, [
		'parameters' => ['dynamic' => new DynamicValue('default')],
	]);
	Assert::same('default', $container->getParameter('dynamic'));

	$class = $container::class;
	$other = new $class(['dynamic' => 'runtime']);
	Assert::same('runtime', $other->getParameter('dynamic'));
});


test('Top-level DynamicValue without a value', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, [
		'parameters' => ['dynamic' => new DynamicValue],
	]);
	Assert::null($container->getParameter('dynamic'));
});


test('Nested DynamicValue is addressed by its dotted path', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, [
		'parameters' => [
			'db' => [
				'host' => 'localhost',
				'password' => new DynamicValue('default'),
			],
		],
	], ['db.password' => 'secret']);
	Assert::same(['host' => 'localhost', 'password' => 'secret'], $container->getParameter('db'));

	// the config default is used when the runtime value is missing
	$class = $container::class;
	$other = new $class;
	Assert::same(['host' => 'localhost', 'password' => 'default'], $other->getParameter('db'));
});


test('DynamicValue referenced from another parameter stays dynamic', function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, [
		'parameters' => [
			'db' => ['password' => new DynamicValue],
			'dsn' => 'pw=%db.password%',
		],
		'services' => ['one' => new DI\Definitions\Statement(Service::class, ['%dsn%'])],
	], ['db.password' => 'secret']);
	Assert::same('pw=secret', $container->getService('one')->arg);
});


test('DynamicValue serializes without its value', function () {
	Assert::same(serialize(new DynamicValue('a')), serialize(new DynamicValue('b')));
	Assert::same(serialize(new DynamicValue), serialize(new DynamicValue('a')));
});


testException('DynamicValue under a dotted key', function () {
	$compiler = new DI\Compiler;
	createContainer($compiler, [
		'parameters' => ['a.b' => ['c' => new DynamicValue]],
	]);
}, Nette\InvalidStateException::class, "Dynamic value cannot be used under a key containing a dot ('a.b.c').");
