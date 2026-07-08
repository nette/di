<?php declare(strict_types=1);

/**
 * Test: ContainerBuilder::hook() defers work into a compilation phase (the DSL time machine),
 * via Nette\DI\Compiler\Schedule. Hooks are registered during compilation (from an extension
 * or, later, a config closure); a standalone builder and misused phases fail loudly.
 */

use Nette\DI;
use Nette\DI\Attributes\Hook;
use Nette\DI\ContainerBuilder;
use Nette\DI\Phase;
use Tester\Assert;
use function Nette\DI\create;

require __DIR__ . '/../bootstrap.php';


class HookLogger
{
	public array $seen = [];
}


class HookOrderExt extends DI\CompilerExtension
{
	public static array $log = [];


	#[Hook(Phase::Modify)]
	public function doModify(ContainerBuilder $builder): void
	{
		self::$log[] = 'ext';
	}
}


/** Runs a given callback with the builder during the Register phase, mimicking a config closure. */
class InlineRegister extends DI\CompilerExtension
{
	public function __construct(
		private \Closure $body,
	) {
	}


	#[Hook(Phase::Register)]
	public function doRegister(ContainerBuilder $builder): void
	{
		($this->body)($builder);
	}
}


test('a builder hook runs in its phase and sees framework services (immediate inside a phase)', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('app', new InlineRegister(function (ContainerBuilder $di) {
		$di->add('logger', create(HookLogger::class));
		$di->hook(Phase::Modify, fn(ContainerBuilder $di) => $di->get('logger')->setup('$seen', [['modified']]));
	}));

	$container = createContainer($compiler);
	Assert::same(['modified'], $container->getService('logger')->seen);
});


test('standalone builder cannot schedule hooks', function () {
	$builder = new ContainerBuilder;
	Assert::exception(
		fn() => $builder->hook(Phase::Modify, fn() => null),
		Nette\InvalidStateException::class,
		'%a%requires compilation via Nette\DI\Compiler%a%',
	);
});


test('hook() rejects the Setup and Compile phases', function () {
	$builder = (new DI\Compiler)->getContainerBuilder();
	Assert::exception(
		fn() => $builder->hook(Phase::Setup, fn() => null),
		Nette\InvalidArgumentException::class,
		'%a%only the Register, Discover and Modify phases%a%',
	);
	Assert::exception(
		fn() => $builder->hook(Phase::Compile, fn() => null),
		Nette\InvalidArgumentException::class,
		'%a%only the Register, Discover and Modify phases%a%',
	);
});


test('scheduling into an already-completed phase throws (past-phase guard)', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('app', new InlineRegister(function (ContainerBuilder $di) {
		// from a Modify hook, reach back into Register (already run) -> loud error
		$di->hook(Phase::Modify, fn(ContainerBuilder $di) => $di->hook(Phase::Register, fn() => null));
	}));

	Assert::exception(
		fn() => $compiler->compile(),
		Nette\InvalidStateException::class,
		'%a%phase Register that has already run%a%',
	);
});


test('a builder hook is ordered against extension hooks via after:', function () {
	HookOrderExt::$log = [];
	$compiler = new DI\Compiler;
	$compiler->addExtension('ext', new HookOrderExt);
	$compiler->addExtension('app', new InlineRegister(function (ContainerBuilder $di) {
		$di->hook(Phase::Modify, fn() => HookOrderExt::$log[] = 'builder', after: HookOrderExt::class);
	}));

	$compiler->compile();
	Assert::same(['ext', 'builder'], HookOrderExt::$log);
});
