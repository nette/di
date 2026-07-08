<?php declare(strict_types=1);

/**
 * Test: Compiler fails loudly instead of silently dropping mis-scheduled hooks.
 * (a) a hook scheduled into the phase currently running would be dropped -> exception;
 * (b) manual hook(Setup, before/after) can never affect extension ordering -> exception;
 * (c) attribute Setup before/after and the intentional double drain of Register still work.
 */

use Nette\DI;
use Nette\DI\Attributes\Hook;
use Nette\DI\ContainerBuilder;
use Nette\DI\Phase;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// (a) scheduling into the running phase throws
class SchedulesIntoRunningPhase extends DI\CompilerExtension
{
	#[Hook(Phase::Register)]
	public function doRegister(ContainerBuilder $builder): void
	{
		$this->hook(Phase::Register, fn() => null); // same phase, mid-drain
	}
}

Assert::exception(
	function () {
		$compiler = new DI\Compiler;
		$compiler->addExtension('x', new SchedulesIntoRunningPhase);
		$compiler->compile();
	},
	Nette\InvalidStateException::class,
	'%a%phase Register while it is running%a%',
);


// (b) manual hook(Setup, before:) throws - Setup ordering is extension-level only
class ManualSetupConstraint extends DI\CompilerExtension
{
	public function registerHooks(): void
	{
		$this->hook(Phase::Setup, fn() => null, before: 'y');
	}
}

Assert::exception(
	function () {
		$compiler = new DI\Compiler;
		$compiler->addExtension('x', new ManualSetupConstraint);
		$compiler->compile();
	},
	Nette\InvalidStateException::class,
	'Setup phase ordering is declared%a%',
);


// (c) attribute Setup before/after is fine, and a Register hook registered after the first
// Register drain (as ServicesExtension does) still runs - the double drain is intentional.
class AttrSetupOrdered extends DI\CompilerExtension
{
	public static array $log = [];


	#[Hook(Phase::Setup, before: '*')]
	public function doSetup(ContainerBuilder $builder): void
	{
		self::$log[] = 'setup';
	}


	#[Hook(Phase::Register)]
	public function doRegister(ContainerBuilder $builder): void
	{
		self::$log[] = 'register';
	}
}

$compiler = new DI\Compiler;
$compiler->addExtension('x', new AttrSetupOrdered);
$compiler->compile();
Assert::same(['setup', 'register'], AttrSetupOrdered::$log);


// (d) a constraint referring to a non-existent class similar to a present one warns (typo);
// a non-existent class with no similar counterpart stays silent (absent optional extension)
class WellKnownExtension extends DI\CompilerExtension
{
	#[Hook(Phase::Modify)]
	public function doModify(ContainerBuilder $builder): void
	{
	}
}

class TypoConstraint extends DI\CompilerExtension
{
	#[Hook(Phase::Modify, before: 'WellKnovnExtension')]
	public function doModify(ContainerBuilder $builder): void
	{
	}
}

Assert::error(
	function () {
		$compiler = new DI\Compiler;
		$compiler->addExtension('known', new WellKnownExtension);
		$compiler->addExtension('typo', new TypoConstraint);
		$compiler->compile();
	},
	E_USER_WARNING,
	"Hook ordering constraint 'before: WellKnovnExtension' refers to a class that does not exist and was ignored, did you mean 'WellKnownExtension'?",
);


class OptionalConstraint extends DI\CompilerExtension
{
	#[Hook(Phase::Modify, after: 'Acme\NotInstalled\AcmeExtension')]
	public function doModify(ContainerBuilder $builder): void
	{
	}
}

Assert::noError(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('known', new WellKnownExtension);
	$compiler->addExtension('optional', new OptionalConstraint);
	$compiler->compile();
});


// (e) handler signature must match the phase: Compile passes ClassType, others the builder
class WrongCompileSignature extends DI\CompilerExtension
{
	#[Hook(Phase::Compile)]
	public function doCompile(ContainerBuilder $builder): void
	{
	}
}

Assert::exception(
	function () {
		$compiler = new DI\Compiler;
		$compiler->addExtension('x', new WrongCompileSignature);
		$compiler->compile();
	},
	Nette\InvalidStateException::class,
	'WrongCompileSignature::doCompile() is a Compile phase handler, so its parameter must accept Nette\PhpGenerator\ClassType, but Nette\DI\ContainerBuilder is declared.',
);


class WrongModifySignature extends DI\CompilerExtension
{
	#[Hook(Phase::Modify)]
	public function doModify(Nette\PhpGenerator\ClassType $class): void
	{
	}
}

Assert::exception(
	function () {
		$compiler = new DI\Compiler;
		$compiler->addExtension('x', new WrongModifySignature);
		$compiler->compile();
	},
	Nette\InvalidStateException::class,
	'%a%doModify() is a Modify phase handler, so its parameter must accept Nette\DI\ContainerBuilder%a%',
);


// (f) an extension registered during Setup with a 'before' constraint that already ran throws;
// an 'after' constraint pointing at an already-run extension is naturally satisfied
class LateFirstBird extends DI\CompilerExtension
{
	#[Hook(Phase::Setup, before: '*')]
	public function doSetup(ContainerBuilder $builder): void
	{
	}
}

class AddsLateFirstBird extends DI\CompilerExtension
{
	#[Hook(Phase::Setup)]
	public function doSetup(ContainerBuilder $builder): void
	{
		$this->compiler->addExtension('lateBird', new LateFirstBird);
	}
}

Assert::exception(
	function () {
		$compiler = new DI\Compiler;
		$compiler->addExtension('adder', new AddsLateFirstBird);
		$compiler->compile();
	},
	Nette\InvalidStateException::class,
	"Extension 'lateBird' declares a Setup hook with before: '*', but it was registered too late - the targeted Setup hooks have already run. Register the extension directly instead of dynamically.",
);


class LateFollower extends DI\CompilerExtension
{
	#[Hook(Phase::Setup, after: Nette\DI\Extensions\ParametersExtension::class)]
	public function doSetup(ContainerBuilder $builder): void
	{
	}
}

class AddsLateFollower extends DI\CompilerExtension
{
	#[Hook(Phase::Setup)]
	public function doSetup(ContainerBuilder $builder): void
	{
		$this->compiler->addExtension('follower', new LateFollower);
	}
}

Assert::noError(function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('adder', new AddsLateFollower);
	$compiler->compile();
});
