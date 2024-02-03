<?php

/**
 * Test: Nette\DI\Compiler: internal functions.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
	public $args;


	public function __construct()
	{
	}


	public function __call($nm, $args)
	{
		$this->args[$nm] = $args;
	}
}


const NUM = 231;

$compiler = new DI\Compiler;
$compiler->setDynamicParameterNames(['dynamic']);
$container = createContainer($compiler, '
parameters:
	t: true
	f: false
	fn: ::constant(NUM)
	not: not(%f%)
	string: string(%f%)

services:
	ok:
		create: Service
		setup:
		  	- not( not(%f%), not(%t%), not(%fn%), not(%dynamic%), %not% )
		  	- string( string(%f%), string(%t%), string(%fn%), string(%dynamic%), %string% )
		  	- bool( bool(%f%), bool(%t%) )
		  	- int( int(%f%), int(%t%), int(%fn%), int(%dynamic%) )
		  	- float( float(%f%), float(%t%), float(%fn%), float(%dynamic%) )

	bad1: Service(bool(123))
	bad2:
		create: Service
		setup:
			- method(bool(123))
', ['dynamic' => 123]);


$obj = $container->getByName('ok');

Assert::same(
	[
		'not' => [true, false, false, false, true],
		'string' => ['0', '1', '231', '123', '0'],
		'bool' => [false, true],
		'int' => [0, 1, 231, 123],
		'float' => [0.0, 1.0, 231.0, 123.0],
	],
	$obj->args,
);

Assert::exception(
	fn() => $container->getByName('bad1'),
	Nette\InvalidStateException::class,
	"Cannot convert '123' to bool.",
);

Assert::exception(
	fn() => $container->getByName('bad2'),
	Nette\InvalidStateException::class,
	"Cannot convert '123' to bool.",
);


// wrong arguments count
Assert::exception(
	fn() => createContainer(new DI\Compiler, '
	services:
		- Service(bool(123, 10))
	'),
	Nette\InvalidStateException::class,
	'Service of type Service: Function bool() expects 1 parameter, 2 given. (used in Service::__construct())',
);
