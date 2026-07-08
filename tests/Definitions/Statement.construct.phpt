<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Definitions\Statement constructor - entity normalization and validation.
 */

use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('plain string entity is kept', function () {
	Assert::same('Foo', (new Statement('Foo'))->getEntity());
});


test('Class::method string is normalized to a tuple', function () {
	Assert::same(['Foo', 'bar'], (new Statement('Foo::bar'))->getEntity());
});


test('PHP literal (contains ?) is not split on ::', function () {
	Assert::same('Foo::CONST . ?', (new Statement('Foo::CONST . ?'))->getEntity());
});


test('@service string is normalized to a Reference', function () {
	$entity = (new Statement('@foo'))->getEntity();
	Assert::type(Reference::class, $entity);
	Assert::same('foo', $entity->getValue());
});


test('@service in a tuple head is normalized to a Reference', function () {
	[$head, $member] = (new Statement(['@foo', 'bar']))->getEntity();
	Assert::type(Reference::class, $head);
	Assert::same('foo', $head->getValue());
	Assert::same('bar', $member);
});


test('null entity is allowed (used for argument-only statements)', function () {
	Assert::null((new Statement(null, [1, 2]))->getEntity());
	Assert::same([1, 2], (new Statement(null, [1, 2]))->arguments);
});


test('Expression in a tuple head is allowed', function () {
	$inner = new Statement('Foo');
	Assert::same([$inner, 'bar'], (new Statement([$inner, 'bar']))->getEntity());
});


test('tuple member is not validated by the constructor (checked later in complete())', function () {
	// documents current behaviour: only the tuple head is type-checked here
	Assert::equal([new Reference('a'), 123], (new Statement([new Reference('a'), 123]))->getEntity());
});


testException('tuple with wrong arity is rejected', function () {
	new Statement(['Foo', 'bar', 'baz']);
}, Nette\InvalidArgumentException::class, 'Argument is not valid Statement entity.');


testException('tuple with a scalar head is rejected', function () {
	new Statement([123, 'method']);
}, Nette\InvalidArgumentException::class, 'Argument is not valid Statement entity.');
