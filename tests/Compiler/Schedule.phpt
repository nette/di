<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler\Schedule - the hook registry, phase state and guards, in isolation.
 */

use Nette\DI\Compiler\Schedule;
use Nette\DI\Phase;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('add + drainSorted returns callbacks and clears them', function () {
	$schedule = new Schedule;
	$schedule->add(Phase::Register, $a = fn() => 'a');
	$schedule->add(Phase::Register, $b = fn() => 'b');

	$drained = $schedule->drainSorted(Phase::Register);
	Assert::same([$a, $b], array_map(fn($h) => $h['callback'], $drained));
	Assert::same([], $schedule->drainSorted(Phase::Register)); // emptied
});


test('before/after ordering', function () {
	$schedule = new Schedule;
	$schedule->add(Phase::Modify, $late = fn() => null, after: '*');
	$schedule->add(Phase::Modify, $early = fn() => null);

	$order = array_map(fn($h) => $h['callback'], $schedule->drainSorted(Phase::Modify));
	Assert::same([$early, $late], $order);
});


test('cannot schedule into the running phase', function () {
	$schedule = new Schedule;
	$schedule->markRunning(Phase::Modify);
	Assert::exception(
		fn() => $schedule->add(Phase::Modify, fn() => null),
		Nette\InvalidStateException::class,
		'%a%while it is running%a%',
	);
	// a different phase is fine
	$schedule->add(Phase::Compile, fn() => null);
	Assert::count(1, $schedule->drainSorted(Phase::Compile));
});


test('cannot schedule into a completed phase', function () {
	$schedule = new Schedule;
	$schedule->markCompleted(Phase::Register);
	Assert::exception(
		fn() => $schedule->add(Phase::Register, fn() => null),
		Nette\InvalidStateException::class,
		'%a%that has already run%a%',
	);
});


test('manual Setup before/after is rejected', function () {
	$schedule = new Schedule;
	Assert::exception(
		fn() => $schedule->add(Phase::Setup, fn() => null, before: 'x'),
		Nette\InvalidStateException::class,
		'Setup phase ordering is declared%a%',
	);
	// Setup without constraints is fine
	$schedule->add(Phase::Setup, fn() => null);
	Assert::count(1, $schedule->drainSorted(Phase::Setup));
});


test('getRunningPhase and clear', function () {
	$schedule = new Schedule;
	Assert::null($schedule->getRunningPhase());
	$schedule->markRunning(Phase::Discover);
	Assert::same(Phase::Discover, $schedule->getRunningPhase());
	$schedule->markIdle();
	Assert::null($schedule->getRunningPhase());

	$schedule->markCompleted(Phase::Register);
	$schedule->add(Phase::Modify, fn() => null);
	$schedule->clear();
	// after clear the completed guard is reset and the queue is empty
	$schedule->add(Phase::Register, fn() => null);
	Assert::count(1, $schedule->drainSorted(Phase::Register));
	Assert::count(0, $schedule->drainSorted(Phase::Modify));
});
