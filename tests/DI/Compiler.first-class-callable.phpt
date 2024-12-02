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
		- Service( Service::foo(...), @a::b()::foo(...), ::trim(...) )
		a: stdClass
	';
	$loader = new DI\Config\Loader;
	$compiler = new DI\Compiler;
	$compiler->addConfig($loader->load(Tester\FileMock::create($config, 'neon')));
	$code = $compiler->compile();

	Assert::contains('new Service(Service::foo(...), $this->getService(\'a\')->b()->foo(...), trim(...));', $code);
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
}, Nette\DI\InvalidConfigurationException::class, "Cannot create closure for 'Service' in config file (used in %a%)");


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
}, Nette\DI\InvalidConfigurationException::class, "Cannot create closure for 'Service' in config file (used in %a%)");
