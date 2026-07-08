<?php declare(strict_types=1);

/**
 * Test: ServiceDefinition creator is an Expression (Statement or first-class callable),
 * and the DynamicParameter / Schema contract of the whole Expression family.
 */

use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expression;
use Nette\DI\Expressions\PartialCall;
use Nette\DI\Expressions\Reference;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('setCreator() wraps plain entities in a Statement', function () {
	$def = (new ServiceDefinition)->setCreator('Foo', [1]);
	Assert::type(Statement::class, $def->getCreator());
	Assert::same('Foo', $def->getEntity());
});


test('setCreator() keeps a first-class callable as-is', function () {
	$callable = new PartialCall(null, 'trim');
	$def = (new ServiceDefinition)->setCreator($callable);
	Assert::same($callable, $def->getCreator());
	Assert::type(Expression::class, $def->getCreator());
	Assert::null($def->getEntity()); // FCC has no Statement-style entity
});


test('getFactory() is an alias of getCreator()', function () {
	$def = (new ServiceDefinition)->setCreator(new PartialCall(null, 'trim'));
	Assert::same($def->getCreator(), $def->getFactory());
});


test('a Reference creator is wrapped in a Statement', function () {
	$def = (new ServiceDefinition)->setCreator(new Reference('svc'));
	Assert::type(Statement::class, $def->getCreator());
	Assert::type(Reference::class, $def->getEntity());
});


testException('arguments cannot be set on a first-class callable creator', function () {
	(new ServiceDefinition)
		->setCreator(new PartialCall(null, 'trim'))
		->setArguments([1]);
}, Nette\InvalidStateException::class, 'Cannot pass arguments to this creator.');


test('__clone deep-copies the creator', function () {
	$def = (new ServiceDefinition)->setCreator('Foo', [new Statement('Bar')]);
	$clone = clone $def;
	Assert::notSame($def->getCreator(), $clone->getCreator());
});


test('Expect::type(Expression) accepts every expression subtype', function () {
	$schema = Expect::structure(['e' => Expect::type(Expression::class)]);
	$processor = new Processor;
	foreach ([new Statement('Foo'), new Reference('a'), new PartialCall(null, 'trim')] as $value) {
		Assert::same($value, $processor->process($schema, ['e' => $value])->e);
	}
});


test('dynamic() schema accepts expressions as DynamicParameter', function () {
	$schema = Expect::structure(['d' => Expect::string()->dynamic()]);
	$processor = new Processor;
	$value = new Statement('Foo');
	Assert::same($value, $processor->process($schema, ['d' => $value])->d);
});
