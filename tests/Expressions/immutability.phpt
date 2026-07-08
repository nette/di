<?php declare(strict_types=1);

/**
 * Test: Expression::complete() leaves the original expression unchanged, so instances can be safely shared.
 */

use Nette\DI;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Shared
{
}

class ConsumerA
{
	public function __construct(Shared $shared)
	{
	}
}

class ConsumerB
{
	public function __construct(Shared $shared)
	{
	}
}


test('shared Statement instance used in two definitions', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('shared')->setType(Shared::class);

	$statement = new Statement('not', ['%dummy%']);
	$builder->addDefinition('a')->setCreator(ConsumerA::class)->addSetup('$prop', [$statement]);
	$builder->addDefinition('b')->setCreator(ConsumerB::class)->addSetup('$prop', [$statement]);

	$builder->complete();

	Assert::same('not', $statement->getEntity()); // the original is untouched
});


test('shared Reference instance used in two definitions', function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('shared')->setType(Shared::class);

	$reference = new Reference('shared');
	$builder->addDefinition('a')->setCreator(ConsumerA::class, [$reference]);
	$builder->addDefinition('b')->setCreator(ConsumerB::class, [$reference]);
	$builder->addDefinition('shared2')->setCreator(Shared::class, []); // not autowired into $reference

	$builder->complete();

	Assert::same('shared', $reference->getValue()); // the original is untouched
});
