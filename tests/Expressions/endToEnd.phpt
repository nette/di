<?php declare(strict_types=1);

/**
 * Test: expressions are not only generated correctly, but behave correctly at runtime.
 * Complements the golden lock (which checks the generated string): here the container is
 * actually instantiated and the resulting services are exercised. Runtime coverage of
 * first-class callables was previously missing entirely.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class RunDep
{
	public $tag;
}


class RunSvc
{
	public array $log = [];
	public $prop;
	public array $items = [];


	public function record(string $msg): void
	{
		$this->log[] = $msg;
	}


	public function makeDep(string $tag): RunDep
	{
		$dep = new RunDep;
		$dep->tag = $tag;
		return $dep;
	}
}


class RunConsumer
{
	public function __construct(
		public mixed $a = null,
		public mixed $b = null,
		public mixed $c = null,
	) {
	}
}


$container = createContainer(new DI\Compiler, '
parameters:
	suffix: "!"

services:
	svc: RunSvc

	# first-class callable as a service (create: ::callable(...))
	trimmer:
		create: ::trim(...)
		autowired: false

	# closure built from a method on another service
	maker: RunConsumer( @svc::makeDep(...) )

	# chained call producing an object, with autowired + expanded inner arg
	chained: RunConsumer( @svc::makeDep(%suffix%) )

	# special functions
	casts: RunConsumer( int("42"), not(false), string(7) )

	# collection of services
	autowiredDep: RunDep
	collector: RunConsumer( typed(RunDep) )

	# setup: property set, appender, method call
	configured:
		create: RunSvc
		setup:
			- $prop = 99
			- \'$items[]\' = alpha
			- \'$items[]\' = beta
			- record(hello)

	tagged1:
		create: RunDep
		tags: [collected]
		autowired: false
	tagged2:
		create: RunDep
		tags: [collected]
		autowired: false

	taggedCollector: RunConsumer( tagged(collected) )
');


test('first-class callable service is a working closure', function () use ($container) {
	$trim = $container->getService('trimmer');
	Assert::type(Closure::class, $trim);
	Assert::same('x', $trim('  x  '));
});


test('closure from method on a service is bound and callable', function () use ($container) {
	$maker = $container->getService('maker');
	Assert::type(Closure::class, $maker->a);
	$dep = ($maker->a)('viaClosure');
	Assert::type(RunDep::class, $dep);
	Assert::same('viaClosure', $dep->tag);
});


test('chained call with autowired + expanded argument returns the right object', function () use ($container) {
	$chained = $container->getService('chained');
	Assert::type(RunDep::class, $chained->a);
	Assert::same('!', $chained->a->tag); // %suffix% expanded
});


test('special functions convert at runtime', function () use ($container) {
	$casts = $container->getService('casts');
	Assert::same(42, $casts->a);       // int("42")
	Assert::true($casts->b);           // not(false)
	Assert::same('7', $casts->c);      // string(7)
});


test('typed() collects all autowired services of the type', function () use ($container) {
	$collector = $container->getService('collector');
	Assert::count(1, $collector->a); // only autowiredDep is an autowired RunDep (tagged1/2 are autowired:false)
	Assert::type(RunDep::class, $collector->a[0]);
});


test('tagged() collects services by tag', function () use ($container) {
	$collector = $container->getService('taggedCollector');
	Assert::count(2, $collector->a);
	Assert::type(RunDep::class, $collector->a[0]);
	Assert::type(RunDep::class, $collector->a[1]);
});


test('setup applies property, appender and method call', function () use ($container) {
	$configured = $container->getService('configured');
	Assert::same(99, $configured->prop);
	Assert::same(['alpha', 'beta'], $configured->items);
	Assert::same(['hello'], $configured->log);
});
