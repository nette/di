<?php declare(strict_types=1);

/**
 * Test: decorator (K6) applies setups containing expressions - a nested call and a
 * first-class callable - to every service of a type, and they work at runtime.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


interface DecoIface
{
}

class DecoA implements DecoIface
{
	public array $log = [];
	public $cb;


	public function tag(string $s): void
	{
		$this->log[] = $s;
	}
}

class DecoB implements DecoIface
{
	public array $log = [];
	public $cb;


	public function tag(string $s): void
	{
		$this->log[] = $s;
	}
}

class DecoHelper
{
	public static function make(): string
	{
		return 'made';
	}
}


$compiler = new DI\Compiler;
$compiler->addExtension('decorator', new DI\Extensions\DecoratorExtension);
$container = createContainer($compiler, "
services:
	a: DecoA
	b: DecoB
decorator:
	DecoIface:
		setup:
			- tag(::strtoupper('hi'))
			- \$cb = DecoHelper::make(...)
");


test('decorator setup with a nested call expression is applied to all matching services', function () use ($container) {
	Assert::same(['HI'], $container->getService('a')->log);
	Assert::same(['HI'], $container->getService('b')->log);
});


test('decorator setup with a first-class callable is applied and works at runtime', function () use ($container) {
	foreach (['a', 'b'] as $name) {
		$cb = $container->getService($name)->cb;
		Assert::type(Closure::class, $cb);
		Assert::same('made', $cb()); // the closure invokes DecoHelper::make()
	}
});
