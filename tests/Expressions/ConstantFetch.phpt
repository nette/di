<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Expressions\ConstantFetch - class constant on the type of the target
 * (the object form of @service::CONSTANT).
 */

use Nette\DI;
use Nette\DI\Expressions\ConstantFetch;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class ConstFixture
{
	public const Version = '1.0';
}


function harness(): array
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('svc')->setType(ConstFixture::class);
	return [new DI\Resolver($builder), new DI\PhpGenerator($builder)];
}


test('complete() resolves the type of a service reference target', function () {
	[$resolver, $generator] = harness();
	$node = new ConstantFetch(new Reference('svc'), 'Version');
	$completed = $node->complete($resolver);
	Assert::type(ConstantFetch::class, $completed);
	Assert::same('ConstFixture', $completed->target);
	Assert::same('ConstFixture::Version', $completed->generateCode($generator));
});


test('complete() resolves a type reference target', function () {
	[$resolver, $generator] = harness();
	$completed = (new ConstantFetch(Reference::fromType(ConstFixture::class), 'Version'))->complete($resolver);
	Assert::same('ConstFixture', $completed->target);
	Assert::same('ConstFixture::Version', $completed->generateCode($generator));
});


test('an already-resolved class target generates directly', function () {
	[, $generator] = harness();
	Assert::same('ConstFixture::Version', (new ConstantFetch('ConstFixture', 'Version'))->generateCode($generator));
});


test('complete() leaves the original untouched (immutability)', function () {
	[$resolver] = harness();
	$node = new ConstantFetch(new Reference('svc'), 'Version');
	$node->complete($resolver);
	Assert::type(Reference::class, $node->target); // original still holds the reference
});


testException('generateCode() before complete() throws', function () {
	[, $generator] = harness();
	(new ConstantFetch(new Reference('svc'), 'Version'))->generateCode($generator);
}, LogicException::class);


test('transformValues() maps target and name, original untouched', function () {
	$node = new ConstantFetch('%cls%', '%name%');
	$map = fn($v) => is_string($v) ? strtr($v, ['%cls%' => 'Foo', '%name%' => 'BAR']) : $v;
	$transformed = $node->transformValues($map);
	Assert::same('Foo', $transformed->target);
	Assert::same('BAR', $transformed->name);
	Assert::same('%cls%', $node->target); // original untouched

	// a non-string result for the name is ignored
	$guarded = $node->transformValues(fn($v) => is_string($v) ? $v : new Reference('x'));
	Assert::same('%name%', $guarded->name);
});
