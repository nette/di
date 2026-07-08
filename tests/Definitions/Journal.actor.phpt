<?php declare(strict_types=1);

/**
 * Test: the journal stamps each mutation with the current actor (which extension / config source
 * made it), pulled from the Schedule during compilation.
 */

use Nette\DI;
use Nette\DI\Attributes\Hook;
use Nette\DI\ContainerBuilder;
use Nette\DI\Phase;
use Tester\Assert;
use function Nette\DI\create;

require __DIR__ . '/../bootstrap.php';


class ActorSvc
{
}

class ClosureService
{
	public bool $touched = false;


	public function __construct(
		public string $name,
	) {
	}
}


class MakerExtension extends DI\CompilerExtension
{
	#[Hook(Phase::Register)]
	public function doRegister(ContainerBuilder $builder): void
	{
		$builder->add('made', create(ActorSvc::class))->tag('t');
	}
}


class DecoratorExt extends DI\CompilerExtension
{
	#[Hook(Phase::Modify)]
	public function doModify(ContainerBuilder $builder): void
	{
		$builder->get('made')->setup('foo');
	}
}


test('mutations are attributed to the extension that made them', function () {
	$compiler = new DI\Compiler;
	$compiler->addExtension('maker', new MakerExtension);
	$compiler->addExtension('deco', new DecoratorExt);
	$compiler->compile();

	$journal = $compiler->getContainerBuilder()->getJournal();

	// created by the maker extension
	Assert::same('maker', $journal->getCreator('made'));

	// the setup step was attributed to the decorator extension
	$bio = $journal->getBiography('made');
	$setup = array_values(array_filter($bio, fn($e) => $e['action'] === 'setup'));
	Assert::same('deco', $setup[0]['actor']);
});


test('config-closure mutations are attributed to config', function () {
	$compiler = new DI\Compiler;
	$compiler->loadConfig(__DIR__ . '/../Config/files/definitions.closure.php');
	$compiler->compile();

	$journal = $compiler->getContainerBuilder()->getJournal();
	Assert::same('config', $journal->getCreator('fromClosure'));
});
