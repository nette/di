<?php

/**
 * Test: ServiceDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


testException('Unknown type', function () {
	$def = new ServiceDefinition;
	$def->setType('Foo');
}, Nette\InvalidArgumentException::class, "Service '': Class or interface 'Foo' not found.");

test('type versus entity I.', function () {
	$def = new ServiceDefinition;
	$def->setType(stdClass::class);
	Assert::same(stdClass::class, $def->getType());
	Assert::null($def->getEntity());

	$def->setArguments([1, 2]);
	Assert::same(stdClass::class, $def->getType());
	Assert::null($def->getEntity());
});

test('type versus entity II.', function () {
	$def = new ServiceDefinition;
	$def->setCreator(stdClass::class);
	Assert::null($def->getType());
	Assert::equal(new Statement(stdClass::class, []), $def->getCreator());

	$def->setArguments([1, 2]);
	Assert::null($def->getType());
	Assert::equal(new Statement(stdClass::class, [1, 2]), $def->getCreator());

	// Demonstrate that setArguments call will always replace arguments.
	$def->setArguments([1 => 200]);
	Assert::equal(new Statement(stdClass::class, [1 => 200]), $def->getCreator());
});

test('Test with factory being previously set.', function () {
	$def1 = new ServiceDefinition;
	$def1->setCreator(stdClass::class, ['foo', 'bar']);
	$def1->setArgument(1, 'new');
	Assert::equal(new Statement(stdClass::class, ['foo', 'new']), $def1->getCreator());

	// Test with factory being set implicitly.
	$def2 = new ServiceDefinition;
	$def2->setArgument(1, 'new');
	$def2->setArgument(2, 'bar');
	Assert::equal(new Statement(null, [1 => 'new', 2 => 'bar']), $def2->getCreator());
});

test('Setting a creator with array arguments', function () {
	$def = new ServiceDefinition;
	$def->setCreator(stdClass::class, [1, 2]);
	Assert::null($def->getType());
	Assert::equal(new Statement(stdClass::class, [1, 2]), $def->getCreator());
});

test('Setting a creator with statement arguments', function () {
	$def = new ServiceDefinition;
	$def->setCreator(new Statement(stdClass::class, [1, 2]));
	Assert::null($def->getType());
	Assert::equal(new Statement(stdClass::class, [1, 2]), $def->getCreator());
});

test('Setting a creator with statement and additional arguments', function () {
	$def = new ServiceDefinition;
	$def->setCreator(new Statement(stdClass::class, [1, 2]), [99]); // 99 is ignored
	Assert::null($def->getType());
	Assert::equal(new Statement(stdClass::class, [1, 2]), $def->getCreator());
});

test('Setting setups with arguments/statements', function () {
	$def = new ServiceDefinition;
	$def->addSetup(stdClass::class, [1, 2]);
	$def->addSetup(new Statement(stdClass::class, [1, 2]));
	$def->addSetup(new Statement(stdClass::class, [1, 2]), [99]); // 99 is ignored
	Assert::equal([
		new Statement(['@self', stdClass::class], [1, 2]),
		new Statement(['@self', stdClass::class], [1, 2]),
		new Statement(['@self', stdClass::class], [1, 2]),
	], $def->getSetup());
});

test('Tag management functionality', function () {
	$def = new ServiceDefinition;
	$def->addTag('tag1');
	$def->addTag('tag2', [1, 2]);
	Assert::equal([
		'tag1' => true,
		'tag2' => [1, 2],
	], $def->getTags());

	Assert::equal(true, $def->getTag('tag1'));
	Assert::equal([1, 2], $def->getTag('tag2'));
	Assert::equal(null, $def->getTag('tag3'));
});

test('deep clone', function () {
	$def = new ServiceDefinition;
	$def->setCreator(new Statement(stdClass::class, [1, 2]));
	$def->addSetup(new Statement(stdClass::class, [1, 2]));

	$dolly = clone $def;
	Assert::notSame($dolly->getCreator(), $def->getCreator());
	Assert::equal($dolly->getCreator(), $def->getCreator());
	Assert::notSame($dolly->getSetup(), $def->getSetup());
	Assert::equal($dolly->getSetup(), $def->getSetup());
});
