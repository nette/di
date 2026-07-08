<?php declare(strict_types=1);

/**
 * Test: a PHP config file can return a closure operating on Definitions (the DSL unlock).
 * The closure runs at load-time (add() immediate, before extensions), hook() defers to a phase.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class ClosureService
{
	public bool $touched = false;


	public function __construct(
		public string $name,
	) {
	}
}


test('closure config file is loaded and compiled', function () {
	$compiler = new DI\Compiler;
	$compiler->loadConfig(__DIR__ . '/files/definitions.closure.php');
	$container = createContainer($compiler->setClassName('ClosureContainer1'));

	$svc = $container->getService('fromClosure');
	Assert::type(ClosureService::class, $svc);
	Assert::same('hello', $svc->name);          // add() + create() arg
	Assert::true($svc->touched);                // Modify hook reached it
});


test('closure config mixes with array/NEON config', function () {
	$compiler = new DI\Compiler;
	$compiler->loadConfig(__DIR__ . '/files/definitions.closure.php');
	$compiler->addConfig(['services' => ['fromArray' => stdClass::class]]);
	$container = createContainer($compiler->setClassName('ClosureContainer2'));

	Assert::true($container->hasService('fromClosure'));
	Assert::type(stdClass::class, $container->getService('fromArray'));
});


test('an unknown config section still errors (the @closures section is exempt)', function () {
	$compiler = new DI\Compiler;
	$compiler->addConfig(['bogusSection' => []]);
	Assert::exception(
		fn() => $compiler->setClassName('ClosureContainer3')->compile(),
		DI\InvalidConfigurationException::class,
		"Found section 'bogusSection'%a%",
	);
});
