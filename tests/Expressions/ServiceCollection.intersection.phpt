<?php declare(strict_types=1);

/**
 * Test: ServiceCollection - union within a dimension, intersection across type and tag,
 * and deduplication by service name (a service matching several criteria appears once).
 */

use Nette\DI;
use Nette\DI\ContainerBuilder;
use Nette\DI\Expressions\Reference;
use Nette\DI\Expressions\ServiceCollection;
use Tester\Assert;
use function Nette\DI\create, Nette\DI\services;

require __DIR__ . '/../bootstrap.php';


interface IA
{
}

interface IB
{
}

class Both implements IA, IB
{
}

class OnlyA implements IA
{
}


/** @return string[] */
function refNames(ServiceCollection $node): array
{
	return array_map(fn(Reference $r) => $r->getValue(), $node->references ?? []);
}


test('multiple types union with dedup: a service implementing both appears once', function () {
	$builder = new ContainerBuilder;
	$builder->addDefinition('both')->setType(Both::class);
	$builder->addDefinition('onlyA')->setType(OnlyA::class);
	$resolver = new DI\Resolver($builder);

	$completed = (new ServiceCollection(types: [IA::class, IB::class]))
		->complete($resolver->withCurrentServiceAvailable());

	Assert::same(['both', 'onlyA'], refNames($completed));
});


test('type and tag together = intersection', function () {
	$builder = new ContainerBuilder;
	$builder->addDefinition('both')->setType(Both::class)->addTag('t');
	$builder->addDefinition('onlyA')->setType(OnlyA::class)->addTag('t');
	$builder->addDefinition('taggedOnly')->setType(stdClass::class)->addTag('t');
	$resolver = new DI\Resolver($builder);

	// IA services intersected with tag 't' -> both, onlyA (stdClass is not IA)
	$completed = (new ServiceCollection(types: [IA::class], tags: ['t']))
		->complete($resolver->withCurrentServiceAvailable());

	Assert::same(['both', 'onlyA'], refNames($completed));
});


test('runtime: services(type:, tag:) collects the intersection', function () {
	$builder = new ContainerBuilder;
	$builder->addDefinition('both')->setType(Both::class)->addTag('t');
	$builder->addDefinition('onlyA')->setType(OnlyA::class);
	$builder->addDefinition('collector')->setCreator(Collector::class, [services(type: IA::class, tag: 't')]);

	$container = createContainer($builder);
	$collector = $container->getService('collector');
	Assert::same([$container->getService('both')], $collector->items);
});


class Collector
{
	public function __construct(
		public array $items,
	) {
	}
}
