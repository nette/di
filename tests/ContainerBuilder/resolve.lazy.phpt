<?php declare(strict_types=1);

/**
 * Test: ContainerBuilder::resolve() is a no-op unless a resolution-relevant mutation happened.
 */

use Nette\DI\ContainerBuilder;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class CountingDefinition extends Nette\DI\Definition
{
	public static int $resolved = 0;


	public function __construct()
	{
		$this->setType(stdClass::class);
	}


	public function resolveType(Nette\DI\Compiler\Resolver $resolver): void
	{
		self::$resolved++;
	}


	public function complete(Nette\DI\Compiler\Resolver $resolver): void
	{
	}
}


test('resolve() runs only when something resolution-relevant changed', function () {
	$builder = new ContainerBuilder;
	$builder->addDefinition('counting', new CountingDefinition);
	$def = $builder->addDefinition('a')->setType(stdClass::class);

	$builder->resolve();
	Assert::same(1, CountingDefinition::$resolved);

	// nothing changed -> no-op
	$builder->resolve();
	Assert::same(1, CountingDefinition::$resolved);

	// setup and tags do not affect resolution -> still a no-op
	$def->addSetup('foo');
	$def->addTag('tag');
	$builder->resolve();
	Assert::same(1, CountingDefinition::$resolved);

	// creator change may change the resolved type -> re-resolves
	$def->setCreator(stdClass::class);
	$builder->resolve();
	Assert::same(2, CountingDefinition::$resolved);

	// autowiring change affects the autowiring index -> re-resolves
	$def->setAutowired(false);
	$builder->resolve();
	Assert::same(3, CountingDefinition::$resolved);

	// adding a definition -> re-resolves
	$builder->addDefinition('b')->setType(stdClass::class);
	$builder->resolve();
	Assert::same(4, CountingDefinition::$resolved);

	// removing a definition -> re-resolves
	$builder->remove('b');
	$builder->resolve();
	Assert::same(5, CountingDefinition::$resolved);
});
