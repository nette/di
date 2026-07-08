<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Expressions\PhpCode - PHP code with ? placeholders.
 * The first specialized node of the Statement dissolution (see docs/expression-roadmap.md).
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\PhpCode;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class LitDep
{
}


function harness(): array
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('dep')->setType(LitDep::class);
	return [new DI\Resolver($builder), new DI\PhpGenerator($builder)];
}


test('resolveType() is unknown for raw PHP code', function () {
	[$resolver] = harness();
	Assert::null((new PhpCode('substr(?, ?)', ['x', 1]))->resolveType($resolver));
});


test('generateCode() substitutes placeholders', function () {
	[, $generator] = harness();
	Assert::same("substr('x', 1)", (new PhpCode('substr(?, ?)', ['x', 1]))->generateCode($generator));
	Assert::same('$service->prop = 5', (new PhpCode('$service->prop = ?', [5]))->generateCode($generator));
});


test('complete() converts @service strings and completes nested expressions, original untouched', function () {
	[$resolver, $generator] = harness();
	$literal = new PhpCode('run(?, ?)', ['@dep', new Statement(LitDep::class)]);
	$completed = $literal->complete($resolver);

	Assert::notSame($literal, $completed);
	Assert::same('@dep', $literal->arguments[0]); // original untouched
	Assert::type(Reference::class, $completed->arguments[0]);
	Assert::same("run(\$this->getService('dep'), new LitDep)", $completed->generateCode($generator));
});


test('transformValues() expands both code and arguments, non-string code result is kept', function () {
	$literal = new PhpCode('run(%mode%, ?)', ['%arg%']);
	$transformed = $literal->transformValues(
		fn($v) => is_string($v)
			? strtr($v, ['%mode%' => 'fast', '%arg%' => 'hello'])
			: (is_array($v) ? array_map(fn($x) => strtr($x, ['%arg%' => 'hello']), $v) : $v),
	);
	Assert::same('run(fast, ?)', $transformed->code);
	Assert::same(['hello'], $transformed->arguments);
	Assert::same('run(%mode%, ?)', $literal->code); // original untouched

	$guarded = $literal->transformValues(fn($v) => is_array($v) ? $v : new Reference('x'));
	Assert::same('run(%mode%, ?)', $guarded->code); // non-string result for code is ignored
});


test('a literal Statement completes to a PhpCode', function () {
	[$resolver, $generator] = harness();
	$statement = new Statement('substr(?, ?)', ['x', 1]);
	$completed = $statement->complete($resolver);

	Assert::type(PhpCode::class, $completed);
	Assert::same("substr('x', 1)", $completed->generateCode($generator));
	Assert::same('substr(?, ?)', $statement->getEntity()); // original untouched
});
