<?php declare(strict_types=1);

/**
 * Test: ContainerBuilder retrieval API get(), find(), has(), all().
 */

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class RetA
{
}

class RetB extends RetA
{
}


function builder(): ContainerBuilder
{
	$builder = new ContainerBuilder;
	$builder->addDefinition('one')->setType(RetA::class)->addTag('t');
	$builder->addDefinition('two')->setType(RetB::class)->addTag('t', 'val');
	return $builder;
}


test('get() by name and by type', function () {
	$b = builder();
	Assert::same('one', $b->get('one')->getName());
	Assert::same('two', $b->get(type: RetB::class)->getName());
});


test('get() asserts the definition class; of: addresses non-ServiceDefinition', function () {
	$b = builder();
	// the implicit 'container' service is an ImportedDefinition, not a ServiceDefinition
	Assert::exception(
		fn() => $b->get('container'),
		Nette\DI\MissingServiceException::class,
		'%a%is a %a%ImportedDefinition%a%',
	);
	Assert::type(
		Nette\DI\Definitions\ImportedDefinition::class,
		$b->get('container', of: Nette\DI\Definitions\ImportedDefinition::class),
	);
	// of: Definition::class accepts anything
	Assert::type(
		Nette\DI\Definitions\ImportedDefinition::class,
		$b->get('container', of: Nette\DI\Definitions\Definition::class),
	);
});


test('find(of:) filters by definition class', function () {
	$b = builder();
	$b->addImportedDefinition('imp')->setType(RetA::class)->addTag('t');

	// without of: all three tagged definitions
	Assert::same(['one', 'two', 'imp'], array_keys($b->find(tag: 't')));
	// of: ServiceDefinition drops the imported one
	Assert::same(['one', 'two'], array_keys($b->find(tag: 't', of: Nette\DI\Definitions\ServiceDefinition::class)));
});


test('get() throws on bad addressing', function () {
	$b = builder();
	Assert::exception(fn() => $b->get(), Nette\InvalidArgumentException::class);
	Assert::exception(fn() => $b->get('one', RetA::class), Nette\InvalidArgumentException::class);
	// RetA matches both one and two -> ambiguous
	Assert::exception(fn() => $b->get(type: RetA::class), Nette\DI\ServiceCreationException::class);
});


test('find() by type returns definitions', function () {
	$b = builder();
	$found = $b->find(type: RetA::class);
	Assert::same(['one', 'two'], array_keys($found));
	Assert::type(Definition::class, $found['one']);
});


test('find() by tag returns definitions, not tag values', function () {
	$b = builder();
	$found = $b->find(tag: 't');
	Assert::same(['one', 'two'], array_keys($found));
	Assert::type(Definition::class, $found['one']);
	Assert::type(Definition::class, $found['two']);
});


test('has() by name and type', function () {
	$b = builder();
	Assert::true($b->has('one'));
	Assert::false($b->has('missing'));
	Assert::true($b->has(type: RetB::class));
	Assert::true($b->has(type: RetA::class)); // multiple still counts as existing
	Assert::false($b->has(type: DateTime::class));
});


test('all() returns every definition', function () {
	$b = builder();
	Assert::same(['container', 'one', 'two'], array_keys($b->getAll()));
});
