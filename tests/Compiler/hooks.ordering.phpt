<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler: ordering of phase hooks.
 */

use Nette\DI;
use Nette\DI\Attributes\Hook;
use Nette\DI\ContainerBuilder;
use Nette\DI\Phase;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Notes
{
	public static array $notes = [];


	public static function add(string $message): void
	{
		self::$notes[] = $message;
	}


	public static function fetch(): array
	{
		$res = self::$notes;
		self::$notes = [];
		return $res;
	}
}


// legacy extensions run in registration order within each phase
class LegacyA extends DI\CompilerExtension
{
	public function loadConfiguration()
	{
		Notes::add('A::load');
	}


	public function beforeCompile()
	{
		Notes::add('A::before');
	}
}

class LegacyB extends DI\CompilerExtension
{
	public function loadConfiguration()
	{
		Notes::add('B::load');
	}


	public function beforeCompile()
	{
		Notes::add('B::before');
	}
}

class LegacyC extends DI\CompilerExtension
{
	public function loadConfiguration()
	{
		Notes::add('C::load');
	}


	public function beforeCompile()
	{
		Notes::add('C::before');
	}
}

test('legacy hooks keep extension registration order, not alphabetical', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('c', new LegacyC);
	$compiler->addExtension('a', new LegacyA);
	$compiler->addExtension('b', new LegacyB);
	createContainer($compiler, '');

	Assert::same([
		'C::load',
		'A::load',
		'B::load',
		'C::before',
		'A::before',
		'B::before',
	], Notes::fetch());
});


// hook with a before constraint jumps ahead of the target regardless of registration order
class ModifyingExtension extends DI\CompilerExtension
{
	#[Hook(Phase::Modify, before: LegacyA::class)]
	public function doModify(ContainerBuilder $builder): void
	{
		Notes::add('Modifying::modify');
	}
}

test('before constraint overrides registration order', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('c', new LegacyC);
	$compiler->addExtension('a', new LegacyA);
	$compiler->addExtension('modifying', new ModifyingExtension);
	createContainer($compiler, '');

	Assert::same([
		'C::load',
		'A::load',
		'C::before',
		'Modifying::modify',
		'A::before',
	], Notes::fetch());
});


// before/after constraints match subclasses of the target extension
class LegacyASubclass extends LegacyA
{
	public function loadConfiguration()
	{
		Notes::add('ASub::load');
	}


	public function beforeCompile()
	{
		Notes::add('ASub::before');
	}
}

test('before constraint matches subclasses of the target', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('c', new LegacyC);
	$compiler->addExtension('aSub', new LegacyASubclass);
	$compiler->addExtension('modifying', new ModifyingExtension);
	createContainer($compiler, '');

	Assert::same([
		'C::load',
		'ASub::load',
		'C::before',
		'Modifying::modify',
		'ASub::before',
	], Notes::fetch());
});


// registerHooks() may add handlers manually via hook() instead of the #[Hook] attribute
class ManualExtension extends DI\CompilerExtension
{
	public function registerHooks(): void
	{
		$this->hook(Phase::Modify, $this->doSecond(...), after: LegacyA::class);
		$this->hook(Phase::Modify, $this->doFirst(...), before: LegacyA::class);
	}


	private function doFirst(ContainerBuilder $builder): void
	{
		Notes::add('Manual::first');
	}


	private function doSecond(ContainerBuilder $builder): void
	{
		Notes::add('Manual::second');
	}
}

test('registerHooks() can add handlers manually via hook() with constraints', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('a', new LegacyA);
	$compiler->addExtension('manual', new ManualExtension);
	createContainer($compiler, '');

	Assert::same([
		'A::load',
		'Manual::first',
		'A::before',
		'Manual::second',
	], Notes::fetch());
});


// two instances of the same extension class are tracked separately
class SetupExtension extends DI\CompilerExtension
{
	#[Hook(Phase::Setup)]
	public function doSetup(ContainerBuilder $builder): void
	{
		Notes::add('Setup::setup ' . $this->name);
	}
}

test('multiple instances of the same extension class keep separate hooks', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('first', new SetupExtension);
	$compiler->addExtension('second', new SetupExtension);
	createContainer($compiler, '');

	Assert::same([
		'Setup::setup first',
		'Setup::setup second',
	], Notes::fetch());
});


// circular constraints are detected
class CycleX extends DI\CompilerExtension
{
	#[Hook(Phase::Modify, before: CycleY::class)]
	public function doModify(ContainerBuilder $builder): void
	{
	}
}

class CycleY extends DI\CompilerExtension
{
	#[Hook(Phase::Modify, before: CycleX::class)]
	public function doModify(ContainerBuilder $builder): void
	{
	}
}

testException('circular dependency is detected', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('x', new CycleX);
	$compiler->addExtension('y', new CycleY);
	createContainer($compiler, '');
}, Nette\InvalidStateException::class, 'Circular dependency detected in extension hooks: CycleX, CycleY');
