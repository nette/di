<?php declare(strict_types=1);

/**
 * Test: ContainerBuilder::remove() is immediate (ADR 0006) and get()/remove() teach the
 * config-time vs. hook() timeline when a service is not found yet.
 */

use Nette\DI;
use Nette\DI\Attributes\Hook;
use Nette\DI\ContainerBuilder;
use Nette\DI\Phase;
use Tester\Assert;
use function Nette\DI\create;

require __DIR__ . '/../bootstrap.php';


class RemA
{
}

class RemB
{
}


class TimelineProbe extends DI\CompilerExtension
{
	public static string $earlyError = '';
	public static string $late = '';


	#[Hook(Phase::Register)]
	public function doRegister(ContainerBuilder $builder): void
	{
		// pretend to be config-time code reaching a service that a later extension will add
		try {
			$builder->get('created.later');
		} catch (DI\MissingServiceException $e) {
			self::$earlyError = $e->getMessage();
		}

		$builder->add('created.later', create(RemA::class));
		$builder->hook(Phase::Modify, function (ContainerBuilder $di) {
			$di->get('created.later'); // now it exists
			self::$late = 'reached';
		});
	}
}


test('remove() by name is immediate; has()/add() stay consistent', function () {
	$b = new ContainerBuilder;
	$b->add('svc', create(RemA::class));
	Assert::true($b->has('svc'));

	// the intuitive if (has()) { remove(); add(); } must just work
	if ($b->has('svc')) {
		$b->remove('svc');
		$b->add('svc', create(RemB::class));
	}

	// has(type:) triggers resolve, so the swap is observable by type
	Assert::false($b->has(type: RemA::class));
	Assert::true($b->has(type: RemB::class));
});


test('remove() by type', function () {
	$b = new ContainerBuilder;
	$b->add('svc', create(RemA::class));
	$b->remove(type: RemA::class);
	Assert::false($b->has('svc'));
});


test('remove() a missing service throws', function () {
	$b = new ContainerBuilder;
	Assert::exception(fn() => $b->remove('missing'), Nette\DI\MissingServiceException::class);
});


test('no timeline hint on a standalone builder (no schedule)', function () {
	$b = new ContainerBuilder;
	$e = Assert::exception(fn() => $b->get('missing'), Nette\DI\MissingServiceException::class);
	Assert::notContains('config time', $e->getMessage());
});


test('get() at config time teaches the timeline; a Modify hook can reach the service', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('probe', new TimelineProbe);

	// config body reaches a framework-like service too early -> teaching error captured by the extension
	$compiler->compile();
	Assert::contains('hook(Phase::Modify', TimelineProbe::$earlyError);
	Assert::same('reached', TimelineProbe::$late);
});
