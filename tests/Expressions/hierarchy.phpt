<?php declare(strict_types=1);

/**
 * Test: locks the Expressions inheritance contract (ADR 0002, 0004).
 * A drift here (e.g. a node silently changing its base) is exactly the class of mismatch that
 * slipped in with PartialCall; this test makes it loud.
 */

use Nette\DI\Definitions\Statement;
use Nette\DI\{Expression, Expressions};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Statement is a final Expression, the base of no node
Assert::same(Expression::class, get_parent_class(Statement::class));
Assert::true((new ReflectionClass(Statement::class))->isFinal());

// every node extends Expression directly
foreach ([
	Expressions\Instantiation::class,
	Expressions\Call::class,
	Expressions\PropertyAccess::class,
	Expressions\PhpCode::class,
	Expressions\SpecialFunction::class,
	Expressions\Reference::class,
	Expressions\ServiceCollection::class,
	Expressions\Expansion::class,
	Expressions\ConstantFetch::class,
	Expressions\PartialCall::class,
] as $node) {
	Assert::same(Expression::class, get_parent_class($node), "$node should extend Expression directly");
	Assert::false(is_subclass_of($node, Statement::class), "$node must NOT be a Statement");
}


// Chaining trait lives only on object-valued nodes and the code() escape hatch (ADR 0004)
Assert::same([Expressions\Chaining::class], array_values(class_uses(Expressions\Reference::class)));
Assert::same([Expressions\Chaining::class], array_values(class_uses(Expressions\Instantiation::class)));
Assert::same([Expressions\Chaining::class], array_values(class_uses(Expressions\Call::class)));
Assert::same([Expressions\Chaining::class], array_values(class_uses(Expressions\PhpCode::class)));

foreach ([
	Expressions\PartialCall::class,
	Expressions\PropertyAccess::class,
	Expressions\ConstantFetch::class,
	Expressions\SpecialFunction::class,
	Expressions\ServiceCollection::class,
	Expressions\Expansion::class,
] as $node) {
	Assert::notContains(Expressions\Chaining::class, array_values(class_uses($node)), "$node must not use Chaining");
}

// the base must never carry chaining
Assert::false(method_exists(Expression::class, 'call'));
