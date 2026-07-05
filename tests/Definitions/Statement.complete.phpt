<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Definitions\Statement::complete() - resolving, autowiring and validation.
 * The original statement must always be left untouched (immutable transformation).
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class ComDep
{
}

class ComSvc
{
	public function __construct(?ComDep $d = null)
	{
	}
}


function resolver(): DI\Resolver
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('dep')->setType(ComDep::class);
	return new DI\Resolver($builder);
}


test('not() is rewritten to a negation call, original untouched', function () {
	$statement = new Statement('not', [true]);
	$completed = $statement->complete(resolver());
	Assert::same('not', $statement->getEntity());       // original untouched
	Assert::same(['', '!'], $completed->getEntity());
});


test('type casts are rewritten to Helpers::convertType()', function () {
	$completed = (new Statement('int', ['42']))->complete(resolver());
	Assert::same([DI\Helpers::class, 'convertType'], $completed->getEntity());
	Assert::same(['42', 'int'], $completed->arguments);
});


test('class constructor is autowired, original arguments untouched', function () {
	$statement = new Statement(ComSvc::class);
	$completed = $statement->complete(resolver());
	Assert::same([], $statement->arguments);            // original untouched
	Assert::count(1, $completed->arguments);
	Assert::type(Reference::class, $completed->arguments[0]);
	Assert::same('dep', $completed->arguments[0]->getValue());
});


test('@service strings in arguments are converted to References', function () {
	$completed = (new Statement(ComSvc::class, ['@dep']))->complete(resolver());
	Assert::type(Reference::class, $completed->arguments[0]);
	Assert::same('dep', $completed->arguments[0]->getValue());
});


test('completing a nested statement leaves the original graph untouched', function () {
	$inner = new Statement(ComSvc::class);
	$outer = new Statement([$inner, 'foo'], []);
	$outer->complete(resolver());
	Assert::same([], $inner->arguments); // nested original not mutated
});


testException('parameters passed to a reference are rejected', function () {
	(new Statement(new Reference('dep'), [1]))->complete(resolver());
}, DI\ServiceCreationException::class, 'Parameters were passed to reference @dep, although references cannot have any parameters.');


testException('unknown class cannot be instantiated', function () {
	(new Statement('NonexistentClass'))->complete(resolver());
}, DI\ServiceCreationException::class, "Class 'NonexistentClass' not found.");
