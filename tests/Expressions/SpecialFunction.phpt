<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Expressions\SpecialFunction - the not() and bool()/int()/float()/string()
 * built-in configuration functions (a shared home for these rare special functions).
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\SpecialFunction;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class SpecDep
{
}


function harness(): array
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('dep')->setType(SpecDep::class);
	return [new DI\Resolver($builder), new DI\PhpGenerator($builder)];
}


test('resolveType() is null (not a service type)', function () {
	[$resolver] = harness();
	Assert::null((new SpecialFunction('not', [true]))->resolveType($resolver));
	Assert::null((new SpecialFunction('int', ['42']))->resolveType($resolver));
});


test('generateCode(): not() negates, casts call the lossless converter', function () {
	[, $generator] = harness();
	Assert::same('!(true)', (new SpecialFunction('not', [true]))->generateCode($generator));
	Assert::same("Nette\\DI\\Helpers::convertType('42', 'int')", (new SpecialFunction('int', ['42']))->generateCode($generator));
	Assert::same("Nette\\DI\\Helpers::convertType(1, 'string')", (new SpecialFunction('string', [1]))->generateCode($generator));
});


test('complete() converts @service value, original untouched', function () {
	[$resolver, $generator] = harness();
	$fn = new SpecialFunction('not', ['@dep']);
	$completed = $fn->complete($resolver);

	Assert::notSame($fn, $completed);
	Assert::same(['@dep'], $fn->arguments); // original untouched
	Assert::same("!(\$this->getService('dep'))", $completed->generateCode($generator));
});


testException('complete(): wrong arity is rejected', function () {
	[$resolver] = harness();
	(new SpecialFunction('not', [1, 2]))->complete($resolver);
}, DI\ServiceCreationException::class, 'Function not() expects 1 parameter, 2 given.');


test('transformValues() maps the arguments, function name preserved', function () {
	$fn = new SpecialFunction('int', ['%num%']);
	$transformed = $fn->transformValues(fn($v) => is_array($v) ? ['42'] : $v);
	Assert::same('int', $transformed->function);
	Assert::same(['42'], $transformed->arguments);
	Assert::same(['%num%'], $fn->arguments); // original untouched
});


test('not()/cast Statements complete to SpecialFunction nodes', function () {
	[$resolver, $generator] = harness();
	foreach ([
		['not', [true], '!(true)'],
		['int', ['42'], "Nette\\DI\\Helpers::convertType('42', 'int')"],
		['bool', ['1'], "Nette\\DI\\Helpers::convertType('1', 'bool')"],
		['float', ['1.5'], "Nette\\DI\\Helpers::convertType('1.5', 'float')"],
		['string', [7], "Nette\\DI\\Helpers::convertType(7, 'string')"],
	] as [$fn, $args, $expected]) {
		$completed = (new Statement($fn, $args))->complete($resolver);
		Assert::type(SpecialFunction::class, $completed);
		Assert::same($expected, $completed->generateCode($generator));
	}
});
