<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Service
{
	public $cb;


	public function __construct($cb)
	{
		$this->cb = $cb;
	}


	public function foo()
	{
	}
}


test('Valid callables', function () {
	$config = '
	services:
		- Service( Service::foo(...), @a::foo(...), ::trim(...) )
		a: stdClass
	';
	$loader = new DI\Config\Loader;
	$compiler = new DI\Compiler;
	$compiler->addConfig($loader->load(Tester\FileMock::create($config, 'neon')));
	$code = $compiler->compile();

	Assert::contains('new Service(Service::foo(...), $this->getService(\'a\')->foo(...), trim(...));', $code);
});


// Invalid callable 1
Assert::exception(function () {
	$config = '
	services:
		- Service(...)
	';
	$loader = new DI\Config\Loader;
	$compiler = new DI\Compiler;
	$compiler->addConfig($loader->load(Tester\FileMock::create($config, 'neon')));
	$compiler->compile();
}, Nette\DI\ServiceCreationException::class, 'Service of type Closure: Cannot create closure for Service(...)');


// Invalid callable 2
Assert::exception(function () {
	$config = '
	services:
		- Service( Service(...) )
	';
	$loader = new DI\Config\Loader;
	$compiler = new DI\Compiler;
	$compiler->addConfig($loader->load(Tester\FileMock::create($config, 'neon')));
	$compiler->compile();
}, Nette\DI\ServiceCreationException::class, 'Service of type Service: Cannot create closure for Service(...) (used in Service::__construct())');
