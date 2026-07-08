<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Expressions\Call - global function, static method and method call
 * on the result of another expression. Mirrors the target shape of PartialCall.
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\Call;
use Nette\DI\Expressions\Instantiation;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class InvDep
{
}

class InvSvc
{
	public function make(InvDep $dep, string $text = ''): InvDep
	{
		return $dep;
	}


	public static function build(InvDep $dep): InvDep
	{
		return $dep;
	}


	private function hidden(): void
	{
	}
}


function invMake(InvDep $dep): InvDep
{
	return $dep;
}


function harness(): array
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('dep')->setType(InvDep::class);
	$builder->addDefinition('svc')->setType(InvSvc::class);
	return [new DI\Resolver($builder), new DI\PhpGenerator($builder)];
}


test('resolveType() follows the return type', function () {
	[$resolver] = harness();
	Assert::same(InvDep::class, (new Call(null, 'invMake'))->resolveType($resolver));
	Assert::same(InvDep::class, (new Call(InvSvc::class, 'build'))->resolveType($resolver));
	Assert::same(InvDep::class, (new Call(new Reference('svc'), 'make'))->resolveType($resolver));
	Assert::exception(
		fn() => (new Call(null, 'trim'))->resolveType($resolver), // built-in return type is not a class
		DI\ServiceCreationException::class,
		"Return type of ::trim() is expected to not be built-in/complex, 'string' given.",
	);
});


test('generateCode(): all target forms', function () {
	[, $generator] = harness();
	Assert::same("trim('x')", (new Call(null, 'trim', ['x']))->generateCode($generator));
	Assert::same('InvSvc::build(1)', (new Call(InvSvc::class, 'build', [1]))->generateCode($generator));
	Assert::same("\$this->getService('svc')->make(1)", (new Call(new Reference('svc'), 'make', [1]))->generateCode($generator));
	Assert::same('(new InvSvc)->make(1)', (new Call(new Instantiation(InvSvc::class), 'make', [1]))->generateCode($generator));
});


test('complete() autowires function and method parameters, original untouched', function () {
	[$resolver, $generator] = harness();

	$invocation = new Call(null, 'invMake');
	$completed = $invocation->complete($resolver);
	Assert::notSame($invocation, $completed);
	Assert::same([], $invocation->arguments); // original untouched
	Assert::same("invMake(\$this->getService('dep'))", $completed->generateCode($generator));

	$completed = (new Call(new Reference('svc'), 'make', [1 => 'hi']))->complete($resolver);
	Assert::same("\$this->getService('svc')->make(\$this->getService('dep'), 'hi')", $completed->generateCode($generator));
});


test('complete() completes an expression target first', function () {
	[$resolver, $generator] = harness();
	$completed = (new Call(new Statement(InvSvc::class), 'make', ['@dep']))->complete($resolver);
	Assert::type(Instantiation::class, $completed->target);
	Assert::same("(new InvSvc)->make(\$this->getService('dep'))", $completed->generateCode($generator));
});


testException('complete(): unknown function', function () {
	[$resolver] = harness();
	(new Call(null, 'unknownFunc'))->complete($resolver);
}, DI\ServiceCreationException::class, "Function unknownFunc doesn't exist.");


testException('complete(): invalid name', function () {
	[$resolver] = harness();
	(new Call(new Reference('svc'), 'foo bar'))->complete($resolver);
}, DI\ServiceCreationException::class, "Expected function, method or property name, 'foo bar' given.");


testException('complete(): non-public method', function () {
	[$resolver] = harness();
	(new Call(new Reference('svc'), 'hidden'))->complete($resolver);
}, DI\ServiceCreationException::class, 'InvSvc::hidden() is not callable.');


testException('complete(): unknown class in static call', function () {
	[$resolver] = harness();
	(new Call('UnknownClass', 'method'))->complete($resolver);
}, DI\ServiceCreationException::class, "Class 'UnknownClass' not found.");


testException('complete(): error in arguments is annotated with the call', function () {
	[$resolver] = harness();
	(new Call(new Reference('svc'), 'make', ['@missing']))->complete($resolver);
}, DI\ServiceCreationException::class, "Reference to missing service 'missing'. (used in @svc::make())");


test('transformValues() maps target, name and arguments, original untouched', function () {
	$map = function ($v) use (&$map) {
		if (is_array($v)) {
			return array_map($map, $v);
		}

		return is_string($v) ? strtr($v, ['%cls%' => 'InvSvc', '%m%' => 'build']) : $v;
	};

	$invocation = new Call('%cls%', '%m%', [1]);
	$transformed = $invocation->transformValues($map);
	Assert::same('InvSvc', $transformed->target);
	Assert::same('build', $transformed->name);
	Assert::same('%m%', $invocation->name); // original untouched

	$guarded = $invocation->transformValues(fn($v) => is_array($v) ? $v : new Reference('x'));
	Assert::same('%m%', $guarded->name); // non-string result for name is ignored
});


test('call Statements complete to Call nodes', function () {
	[$resolver, $generator] = harness();
	foreach ([
		[new Statement(['', 'invMake']), "invMake(\$this->getService('dep'))"],
		[new Statement('InvSvc::build'), "InvSvc::build(\$this->getService('dep'))"],
		[new Statement([new Reference('svc'), 'make'], [1 => 'x']), "\$this->getService('svc')->make(\$this->getService('dep'), 'x')"],
		[new Statement([new Statement(InvSvc::class), 'make'], [1 => 'x']), "(new InvSvc)->make(\$this->getService('dep'), 'x')"],
	] as [$statement, $expected]) {
		$completed = $statement->complete($resolver);
		Assert::type(Call::class, $completed);
		Assert::same($expected, $completed->generateCode($generator));
	}
});


test('a plain reference creator completes to a non-shared Reference (fresh factory call)', function () {
	[$resolver, $generator] = harness();
	$completed = (new Statement(new Reference('svc')))->complete($resolver);
	Assert::type(Reference::class, $completed);
	Assert::false($completed->shared);
	Assert::same('$this->createServiceSvc()', $completed->generateCode($generator));
});
