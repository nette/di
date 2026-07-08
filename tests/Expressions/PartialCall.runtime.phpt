<?php declare(strict_types=1);

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


class Chained
{
	public function next(stdClass $dep, string $text): self
	{
		return $this;
	}


	public function foo()
	{
	}
}


function compileCode(string $config): string
{
	$loader = new DI\Config\Loader;
	$compiler = new DI\Compiler;
	$compiler->addConfig($loader->load(Tester\FileMock::create($config, 'neon')));
	return $compiler->compile();
}


test('Valid callables', function () {
	$code = compileCode('
	services:
		- Service( Service::foo(...), @a::foo(...), ::trim(...) )
		a: stdClass
	');

	Assert::contains('new Service(Service::foo(...), $this->getService(\'a\')->foo(...), trim(...));', $code);
});


test('Namespaced function', function () {
	$code = compileCode('
	services:
		- Service( ::Foo\bar(...) )
	');

	Assert::contains('new Service(Foo\bar(...));', $code);
});


test('Callable on freshly created instance', function () {
	$code = compileCode('
	services:
		- Service( Chained()::foo(...) )
	');

	Assert::contains('new Service((new Chained)->foo(...));', $code);
});


test('Chained callable with autowired & expanded inner arguments', function () {
	$code = compileCode('
	parameters:
		text: hello

	services:
		a: Chained
		- stdClass
		- Service( @a::next(text: %text%)::foo(...) )
	');

	Assert::contains("new Service(\$this->getService('a')->next(\$this->getService('01'), 'hello')->foo(...));", $code);
});


test('Method call chained after callable', function () {
	$code = compileCode('
	services:
		a: Chained
		- Service( @a::foo(...)::bindTo(null) )
	');

	Assert::contains("new Service(\$this->getService('a')->foo(...)->bindTo(null));", $code);
});


test('Callable as service creator', function () {
	$code = compileCode('
	services:
		fn: {create: ::trim(...), autowired: false}
		- Service(@fn)
	');

	Assert::contains('public function createServiceFn(): Closure', $code);
	Assert::contains('return trim(...);', $code);
});


test('Callable in extension config validated by schema', function () {
	$extension = new class extends DI\CompilerExtension {
		public function getConfigSchema(): Nette\Schema\Schema
		{
			return Nette\Schema\Expect::structure([
				'callback' => Nette\Schema\Expect::type(DI\Expression::class),
			]);
		}
	};
	$loader = new DI\Config\Loader;
	$compiler = new DI\Compiler;
	$compiler->addExtension('my', $extension);
	$compiler->addConfig($loader->load(Tester\FileMock::create('
	my:
		callback: ::trim(...)
	', 'neon')));
	$compiler->compile();

	Assert::type(DI\Expressions\PartialCall::class, $extension->getConfig()->callback);
});


// Invalid callable 1
Assert::exception(function () {
	compileCode('
	services:
		- Service(...)
	');
}, Nette\DI\InvalidConfigurationException::class, "Cannot create closure for 'Service(...)' in config file (used in %a%)");


// Invalid callable 2
Assert::exception(function () {
	compileCode('
	services:
		- Service( Service(...) )
	');
}, Nette\DI\InvalidConfigurationException::class, "Cannot create closure for 'Service(...)' in config file (used in %a%)");


// Invalid method name
Assert::exception(function () {
	compileCode('
	parameters:
		method: "foo bar"

	services:
		a: stdClass
		- Service( @a::%method%(...) )
	');
}, Nette\DI\ServiceCreationException::class, "%a?%Expected a valid method name, 'foo bar' given.%a?%");
