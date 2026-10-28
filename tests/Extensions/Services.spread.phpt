<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler and the spread operator ...() in parameters and arguments.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Service
{
	public array $args;


	public function __construct(...$args)
	{
		$this->args = $args;
	}
}


test('items of an array parameter are spread into an array', function () {
	$container = createContainer(new DI\Compiler, '
parameters:
	data1:
		username: root
		password: 123
	data2:
		- ...(%data1%)
		database: app
	data3:
		- a
		- b

services:
	s1: Service([timezone: utc, username: new, ...(%data2%), database: new])
	s2: Service(...(%data3%))
');

	Assert::same([
		'username' => 'root',
		'password' => 123,
		'database' => 'app',
	], $container->getParameters()['data2']);

	Assert::same([[
		'timezone' => 'utc',
		'username' => 'root',
		'password' => 123,
		'database' => 'new',
	]], $container->getService('s1')->args);

	Assert::same(['a', 'b'], $container->getService('s2')->args);
});


test('only an array known at compile time can be spread', function () {
	$compiler = new DI\Compiler;
	$compiler->setDynamicParameterNames(['dynamic']);
	Assert::exception(
		fn() => createContainer($compiler, '
services:
	s: Service(...(%dynamic%))
', ['dynamic' => [1]]),
		Nette\InvalidArgumentException::class,
		"Spread operator ...() expects an array known at compile time, Nette\\DI\\Compiler\\DynamicParameter given in 'services\u{a0}›\u{a0}s'.",
	);
});
