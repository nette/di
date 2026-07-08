<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Expressions\ServiceCollection.
 * They collect all matching services into an array, excluding the current service.
 */

use Nette\DI;
use Nette\DI\Expressions\ServiceCollection;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class CollDep
{
}

class CollConsumer
{
	/** @param CollDep[] $items */
	public function __construct(
		public array $items = [],
	) {
	}
}


function builder(): DI\ContainerBuilder
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')->setType(CollDep::class)->addTag('mark');
	$builder->addDefinition('b')->setType(CollDep::class)->addTag('mark');
	$builder->addDefinition('c')->setType(stdClass::class);
	return $builder;
}


test('resolveType() is unknown (a collection is an array)', function () {
	$resolver = new DI\Resolver(builder());
	Assert::null((new ServiceCollection(types: [CollDep::class]))->resolveType($resolver));
});


test('by types collects all autowired services of the type', function () {
	$builder = builder();
	$generator = new DI\PhpGenerator($builder);
	$completed = (new ServiceCollection(types: [CollDep::class]))->complete(new DI\Resolver($builder));

	Assert::type(ServiceCollection::class, $completed);
	Assert::same("[\$this->getService('a'), \$this->getService('b')]", $completed->generateCode($generator));
});


test('by tags collects all services with the tag', function () {
	$builder = builder();
	$generator = new DI\PhpGenerator($builder);
	$completed = (new ServiceCollection(tags: ['mark']))->complete(new DI\Resolver($builder));

	Assert::same("[\$this->getService('a'), \$this->getService('b')]", $completed->generateCode($generator));
});


test('the current service is excluded from the collection', function () {
	$builder = builder();
	$generator = new DI\PhpGenerator($builder);
	$resolver = (new DI\Resolver($builder))->withCurrentService($builder->getDefinition('a'));
	$completed = (new ServiceCollection(types: [CollDep::class]))->complete($resolver);

	Assert::same("[\$this->getService('b')]", $completed->generateCode($generator));
});


test('an empty collection generates an empty array', function () {
	$builder = builder();
	$generator = new DI\PhpGenerator($builder);
	$completed = (new ServiceCollection(tags: ['nonexistent']))->complete(new DI\Resolver($builder));
	Assert::same('[]', $completed->generateCode($generator));
});


test('transformValues() maps types and tags, original untouched', function () {
	$coll = new ServiceCollection(types: ['%type%'], tags: ['%tag%']);
	$transformed = $coll->transformValues(fn($v) => array_map(fn($x) => strtr($x, ['%type%' => CollDep::class, '%tag%' => 'mark']), $v));
	Assert::same([CollDep::class], $transformed->types);
	Assert::same(['mark'], $transformed->tags);
	Assert::same(['%type%'], $coll->types); // original untouched
	Assert::same(['%tag%'], $coll->tags);
});


test('typed()/tagged() in service arguments produce array-valued arguments', function () {
	$container = createContainer(new DI\Compiler, '
	services:
		a:
			create: CollDep
			tags: [mark]
		b:
			create: CollDep
			tags: [mark]
		typedConsumer: CollConsumer(typed(CollDep))
		taggedConsumer: CollConsumer(tagged(mark))
	');

	Assert::count(2, $container->getService('typedConsumer')->items);
	Assert::type(CollDep::class, $container->getService('typedConsumer')->items[0]);
	Assert::count(2, $container->getService('taggedConsumer')->items);
});
