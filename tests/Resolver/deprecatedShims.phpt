<?php declare(strict_types=1);

/**
 * Test: deprecated shims Resolver::completeStatement() & co. keep working and match the new API.
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class ShimDep
{
}

class ShimService
{
	public function __construct(ShimDep $dep)
	{
	}
}


$builder = new DI\ContainerBuilder;
$depDef = $builder->addDefinition('dep')->setType(ShimDep::class);
$resolver = new DI\Resolver($builder);
$generator = new DI\PhpGenerator($builder);


test('resolveEntityType() delegates to Statement::resolveType()', function () use ($resolver) {
	Assert::same(ShimService::class, $resolver->resolveEntityType(new Statement(ShimService::class)));
});


test('resolveReferenceType() delegates to Reference::resolveType()', function () use ($resolver) {
	Assert::same(ShimDep::class, $resolver->resolveReferenceType(new Reference('dep')));
});


test('resolveReference() returns the definition', function () use ($resolver, $depDef) {
	Assert::same($depDef, $resolver->resolveReference(new Reference('dep')));
});


test('normalizeReference() resolves type-based reference to named one', function () use ($resolver) {
	$normalized = $resolver->normalizeReference(Reference::fromType(ShimDep::class));
	Assert::same('dep', $normalized->getValue());
});


test('completeStatement() returns completed statement, original untouched', function () use ($resolver) {
	$statement = new Statement(ShimService::class);
	$completed = $resolver->completeStatement($statement);
	Assert::notSame($statement, $completed);
	Assert::same([], $statement->arguments); // original untouched
	Assert::equal([new Reference('dep')], $completed->arguments); // autowired
});


test('completeArguments() completes nested expressions', function () use ($resolver) {
	$completed = $resolver->completeArguments([Reference::fromType(ShimDep::class)]);
	Assert::equal([new Reference('dep')], $completed);
});


test('PhpGenerator::formatStatement() delegates to Statement::generateCode()', function () use ($generator) {
	Assert::same('new ShimDep', $generator->formatStatement(new Statement(ShimDep::class)));
});
