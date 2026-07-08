<?php declare(strict_types=1);

/**
 * Test: NeonAdapter builds the right expression node objects (parse-shape lock) and
 * dumps them back. This is the parsing counterpart of the golden generation lock.
 */

use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
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


test('dump round-trips expression nodes', function () {
	$adapter = new NeonAdapter;
	$strip = fn(string $s): string => trim(preg_replace('#^#m', '', explode("\n\n", $s, 2)[1] ?? $s));

	Assert::same('x: Foo(1, @svc)', $strip($adapter->dump(['x' => new Statement('Foo', [1, new Reference('svc')])])));
});
