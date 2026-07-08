<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Expressions\Instantiation - class instantiation with constructor autowiring.
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\Instantiation;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class InstDep
{
}

class InstSvc
{
	public function __construct(InstDep $dep, string $text = 'default')
	{
	}
}

class InstNoCtor
{
}

abstract class InstAbstract
{
}

class InstPrivateCtor
{
	private function __construct()
	{
	}
}


function harness(): array
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('dep')->setType(InstDep::class);
	return [new DI\Resolver($builder), new DI\PhpGenerator($builder)];
}


test('resolveType() is the class itself', function () {
	[$resolver] = harness();
	Assert::same(InstSvc::class, (new Instantiation(InstSvc::class))->resolveType($resolver));
});


test('generateCode() with and without arguments', function () {
	[, $generator] = harness();
	Assert::same('new InstNoCtor', (new Instantiation(InstNoCtor::class))->generateCode($generator));
	Assert::same("new InstSvc(1, 'x')", (new Instantiation(InstSvc::class, [1, 'x']))->generateCode($generator));
});


test('complete() autowires the constructor, original untouched', function () {
	[$resolver, $generator] = harness();
	$instantiation = new Instantiation(InstSvc::class);
	$completed = $instantiation->complete($resolver);

	Assert::notSame($instantiation, $completed);
	Assert::same([], $instantiation->arguments); // original untouched
	Assert::type(Reference::class, $completed->arguments[0]);
	Assert::same("new InstSvc(\$this->getService('dep'))", $completed->generateCode($generator));
});


test('complete() converts @service strings in arguments', function () {
	[$resolver] = harness();
	$completed = (new Instantiation(InstSvc::class, ['@dep', 'hi']))->complete($resolver);
	Assert::type(Reference::class, $completed->arguments[0]);
	Assert::same('hi', $completed->arguments[1]);
});


testException('complete(): unknown class', function () {
	[$resolver] = harness();
	(new Instantiation('UnknownClass'))->complete($resolver);
}, DI\ServiceCreationException::class, "Class 'UnknownClass' not found.");


testException('complete(): abstract class', function () {
	[$resolver] = harness();
	(new Instantiation(InstAbstract::class))->complete($resolver);
}, DI\ServiceCreationException::class, 'Class InstAbstract is abstract.');


testException('complete(): private constructor', function () {
	[$resolver] = harness();
	(new Instantiation(InstPrivateCtor::class))->complete($resolver);
}, DI\ServiceCreationException::class, 'Class InstPrivateCtor has private constructor.');


testException('complete(): arguments to a class without constructor', function () {
	[$resolver] = harness();
	(new Instantiation(InstNoCtor::class, [1]))->complete($resolver);
}, DI\ServiceCreationException::class, 'Unable to pass arguments, class InstNoCtor has no constructor.');


testException('complete(): error in arguments is annotated with the constructor', function () {
	[$resolver] = harness();
	(new Instantiation(InstSvc::class, ['@missing']))->complete($resolver);
}, DI\ServiceCreationException::class, "Reference to missing service 'missing'. (used in InstSvc::__construct())");


test('transformValues() maps class and arguments, original untouched', function () {
	$map = function ($v) use (&$map) {
		if (is_array($v)) {
			return array_map($map, $v);
		}

		return is_string($v) ? strtr($v, ['%cls%' => 'InstSvc', '%arg%' => 'x']) : $v;
	};

	$instantiation = new Instantiation('%cls%', ['%arg%']);
	$transformed = $instantiation->transformValues($map);
	Assert::same('InstSvc', $transformed->class);
	Assert::same(['x'], $transformed->arguments);
	Assert::same('%cls%', $instantiation->class); // original untouched

	$guarded = $instantiation->transformValues(fn($v) => is_array($v) ? $v : new Reference('x'));
	Assert::same('%cls%', $guarded->class); // non-string result for class is ignored
});


test('a class Statement completes to an Instantiation', function () {
	[$resolver, $generator] = harness();
	$statement = new Statement(InstSvc::class, ['@dep', 'hello']);
	$completed = $statement->complete($resolver);

	Assert::type(Instantiation::class, $completed);
	Assert::same("new InstSvc(\$this->getService('dep'), 'hello')", $completed->generateCode($generator));
	Assert::same(['@dep', 'hello'], $statement->arguments); // original untouched
});
