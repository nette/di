<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Helpers::sortByConstraints()
 */

use Nette\DI\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
}

class Bar
{
}

class Baz
{
}

class FooChild extends Foo
{
}


function item(?object $owner, string|array|null $before = null, string|array|null $after = null): array
{
	return ['owner' => $owner, 'before' => $before, 'after' => $after];
}


test('empty input', function () {
	Assert::same([], Helpers::sortByConstraints([]));
});


test('no constraints keep registration order, not alphabetical', function () {
	Assert::same(['c', 'a', 'b'], Helpers::sortByConstraints([
		'c' => item(new Baz),
		'a' => item(new Foo),
		'b' => item(new Bar),
	]));
});


test('before target jumps ahead of it', function () {
	Assert::same(['mover', 'foo'], Helpers::sortByConstraints([
		'foo' => item(new Foo),
		'mover' => item(new Bar, before: Foo::class),
	]));
});


test('after target falls behind it', function () {
	Assert::same(['foo', 'mover'], Helpers::sortByConstraints([
		'mover' => item(new Bar, after: Foo::class),
		'foo' => item(new Foo),
	]));
});


test('targets match subclasses via is_a', function () {
	Assert::same(['mover', 'child'], Helpers::sortByConstraints([
		'child' => item(new FooChild),
		'mover' => item(new Bar, before: Foo::class),
	]));
});


test('array of before targets', function () {
	Assert::same(['mover', 'foo', 'bar'], Helpers::sortByConstraints([
		'foo' => item(new Foo),
		'bar' => item(new Bar),
		'mover' => item(new Baz, before: [Foo::class, Bar::class]),
	]));
});


test('missing target is a no-op', function () {
	Assert::same(['a', 'mover'], Helpers::sortByConstraints([
		'a' => item(new Bar),
		'mover' => item(new Baz, before: Foo::class),
	]));
});


test('wildcard before runs first', function () {
	Assert::same(['first', 'a', 'b'], Helpers::sortByConstraints([
		'a' => item(new Foo),
		'b' => item(new Bar),
		'first' => item(new Baz, before: '*'),
	]));
});


test('wildcard after runs last', function () {
	Assert::same(['a', 'b', 'last'], Helpers::sortByConstraints([
		'last' => item(new Baz, after: '*'),
		'a' => item(new Foo),
		'b' => item(new Bar),
	]));
});


test('two wildcard-before items keep their mutual order', function () {
	Assert::same(['w1', 'w2', 'normal'], Helpers::sortByConstraints([
		'w1' => item(new Foo, before: '*'),
		'normal' => item(new Bar),
		'w2' => item(new Baz, before: '*'),
	]));
});


test('wildcard also orders items with a null owner', function () {
	Assert::same(['plain', 'last'], Helpers::sortByConstraints([
		'last' => item(new Bar, after: '*'),
		'plain' => item(null),
	]));
});


test('a class target never matches a null owner', function () {
	Assert::same(['plain', 'mover'], Helpers::sortByConstraints([
		'plain' => item(null),
		'mover' => item(new Bar, before: Foo::class),
	]));
});


test('numeric keys are supported (as used for hooks)', function () {
	Assert::same([1, 0], Helpers::sortByConstraints([
		0 => item(new Foo),
		1 => item(new Bar, before: Foo::class),
	]));
});


test('circular dependency is reported with the owner class names', function () {
	Assert::exception(
		fn() => Helpers::sortByConstraints([
			'x' => item(new Foo, before: Bar::class),
			'y' => item(new Bar, before: Foo::class),
		]),
		Nette\InvalidStateException::class,
		'Circular dependency detected in extension hooks: Foo, Bar',
	);
});
