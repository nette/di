<?php

/**
 * Test: Nette\DI\Compiler: internal functions.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Tester\Assert;
use Tester\Expect;


require __DIR__ . '/../bootstrap.php';


class Service
{
	public function __construct()
	{
	}


	public function __call($nm, $args)
	{
		$this->args[$nm] = $args;
	}
}


// bad conversion
Assert::exception(function () {
	createContainer(new DI\Compiler, '
	services:
		- Service(bool(123))
	');
}, Nette\InvalidStateException::class, "Service of type Service: Cannot convert '123' to bool.");


Assert::exception(function () {
	createContainer(new DI\Compiler, '
	services:
		-
			factory: Service
			setup:
				- method(bool(123))
	');
}, Nette\InvalidStateException::class, "Service of type Service: Cannot convert '123' to bool.");




// correct conversion
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
		factory: Service
		setup:
		  	- not( not(%f%), not(%t%), not(%fn%), not(%dynamic%), %not% )
		  	- string( string(%f%), string(%t%), string(%fn%), string(%dynamic%), %string% )
		  	- bool( bool(%f%), bool(%t%) )
		  	- int( int(%f%), int(%t%), int(%fn%), int(%dynamic%) )
		  	- float( float(%f%), float(%t%), float(%fn%), float(%dynamic%) )
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
	$obj->args
);


// extension
class Extension extends DI\CompilerExtension
{
	public function loadConfiguration()
	{
	}
}

$compiler = new DI\Compiler;
$extension = new Extension;
$compiler->addExtension('extension', $extension);
$compiler->setDynamicParameterNames(['dynamic']);
$container = createContainer($compiler, '
parameters:
	t: true
	f: false
	fn: ::constant(NUM)
	not: not(%f%)
	string: string(%f%)

extension:
  	- [ not(%f%), not(%t%), not(%fn%), not(%dynamic%), %not% ]
  	- [ string(%f%), string(%t%), string(%fn%), string(%dynamic%), %string% ]
  	- [ bool(%f%), bool(%t%) ]
  	- [ int(%f%), int(%t%), int(%fn%), int(%dynamic%) ]
  	- [ float(%f%), float(%t%), float(%fn%), float(%dynamic%) ]
', ['dynamic' => 123]);


Assert::equal(
	[
		[true, false, Expect::type(Statement::class), Expect::type(Statement::class), true],
		['0', '1', Expect::type(Statement::class), Expect::type(Statement::class), '0'],
		[false, true],
		[0, 1, Expect::type(Statement::class), Expect::type(Statement::class)],
		[0.0, 1.0, Expect::type(Statement::class), Expect::type(Statement::class)],
	],
	$extension->getConfig()
);


// wrong arguments count
Assert::exception(function () {
	createContainer(new DI\Compiler, '
	services:
		- Service(bool(123, 10))
	');
}, Nette\InvalidStateException::class, 'Service of type Service: Function bool() expects at most 1 parameter, 2 given.');

Assert::exception(function () {
	createContainer(new DI\Compiler, '
	extension:
	  	- not(1, 2)
	');
}, Nette\InvalidStateException::class, 'Function not() expects at most 1 parameter, 2 given.');
