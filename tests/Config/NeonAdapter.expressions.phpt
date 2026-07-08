<?php declare(strict_types=1);

/**
 * Test: NeonAdapter builds the right expression node objects (parse-shape lock) and
 * dumps them back. This is the parsing counterpart of the golden generation lock.
 */

use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\PartialCall;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function parseService(string $entity)
{
	$adapter = new NeonAdapter;
	$data = $adapter->load(Tester\FileMock::create("services:\n\tx: $entity\n", 'neon'));
	return $data['services']['x'];
}


test('bare class stays a scalar string (wrapped later by the schema)', function () {
	Assert::same('Foo', parseService('Foo'));
});


test('call with arguments becomes a Statement', function () {
	$node = parseService('Foo::create(1)');
	Assert::type(Statement::class, $node);
	Assert::same(['Foo', 'create'], $node->getEntity());
	Assert::same([1], $node->arguments);
});


test('global function first-class callable', function () {
	$node = parseService('::trim(...)');
	Assert::type(PartialCall::class, $node);
	Assert::null($node->target);
	Assert::same('trim', $node->name);
});


test('static method first-class callable', function () {
	$node = parseService('Foo::bar(...)');
	Assert::type(PartialCall::class, $node);
	Assert::same('Foo', $node->target);
	Assert::same('bar', $node->name);
});


test('method-on-reference first-class callable', function () {
	$node = parseService('@svc::method(...)');
	Assert::type(PartialCall::class, $node);
	Assert::type(Reference::class, $node->target);
	Assert::same('svc', $node->target->getValue());
	Assert::same('method', $node->name);
});


test('chained first-class callable keeps the inner chain as a Statement', function () {
	$node = parseService('@svc::chain()::method(...)');
	Assert::type(PartialCall::class, $node);
	Assert::type(Statement::class, $node->target);
	[$head, $member] = $node->target->getEntity();
	Assert::type(Reference::class, $head);
	Assert::same('svc', $head->getValue());
	Assert::same('chain', $member);
	Assert::same('method', $node->name);
});


test('dump round-trips expression nodes', function () {
	$adapter = new NeonAdapter;
	$strip = fn(string $s): string => trim(preg_replace('#^#m', '', explode("\n\n", $s, 2)[1] ?? $s));

	Assert::same('x: ::trim(...)', $strip($adapter->dump(['x' => new PartialCall(null, 'trim')])));
	Assert::same('x: Foo::bar(...)', $strip($adapter->dump(['x' => new PartialCall('Foo', 'bar')])));
	Assert::same('x: @svc::m(...)', $strip($adapter->dump(['x' => new PartialCall(new Reference('svc'), 'm')])));
	Assert::same('x: Foo(1, @svc)', $strip($adapter->dump(['x' => new Statement('Foo', [1, new Reference('svc')])])));
});
