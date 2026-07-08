<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Expressions\PropertyAccess - property read, assignment and append.
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\PropertyAccess;
use Nette\DI\Expressions\PropertyMode;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class PropDep
{
	public $prop;
	public stdClass $obj;
	public int $num = 0;
}


function harness(): array
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')->setType(PropDep::class);
	return [new DI\Resolver($builder), new DI\PhpGenerator($builder)];
}


test('resolveType(): read reflects the property type, write reflects the assigned value', function () {
	[$resolver] = harness();
	// read: type from the property reflection
	Assert::same(stdClass::class, (new PropertyAccess(new Reference('a'), 'obj'))->resolveType($resolver));
	// an untyped or scalar property has no service type
	Assert::null((new PropertyAccess(new Reference('a'), 'prop'))->resolveType($resolver));
	Assert::null((new PropertyAccess(new Reference('a'), 'num'))->resolveType($resolver));
	// a missing property (e.g. magic __get) -> null
	Assert::null((new PropertyAccess(new Reference('a'), 'missing'))->resolveType($resolver));
	// write: type of the assigned value
	Assert::same(stdClass::class, (new PropertyAccess(new Reference('a'), 'obj', PropertyMode::Assign, new Statement(stdClass::class)))->resolveType($resolver));
	Assert::null((new PropertyAccess(new Reference('a'), 'obj', PropertyMode::Assign, 5))->resolveType($resolver));
});


test('generateCode(): read, assign, append on a reference', function () {
	[, $generator] = harness();
	Assert::same("\$this->getService('a')->prop", (new PropertyAccess(new Reference('a'), 'prop'))->generateCode($generator));
	Assert::same("\$this->getService('a')->prop = 5", (new PropertyAccess(new Reference('a'), 'prop', PropertyMode::Assign, 5))->generateCode($generator));
	Assert::same("\$this->getService('a')->items[] = 5", (new PropertyAccess(new Reference('a'), 'items', PropertyMode::Append, 5))->generateCode($generator));
	Assert::same('$service->prop = 1', (new PropertyAccess(new Reference(Reference::Self), 'prop', PropertyMode::Assign, 1))->generateCode($generator));
});


test('a read and an assignment of null are distinguished', function () {
	[, $generator] = harness();
	Assert::same("\$this->getService('a')->prop", (new PropertyAccess(new Reference('a'), 'prop', PropertyMode::Read))->generateCode($generator));
	Assert::same("\$this->getService('a')->prop = null", (new PropertyAccess(new Reference('a'), 'prop', PropertyMode::Assign, null))->generateCode($generator));
});


test('generateCode(): static property keeps the historical quoted form', function () {
	[, $generator] = harness();
	Assert::same("'PropDep'::\$prop = 5", (new PropertyAccess('PropDep', 'prop', PropertyMode::Assign, 5))->generateCode($generator));
});


test('complete() converts @service value and completes nested expressions, original untouched', function () {
	[$resolver, $generator] = harness();
	$access = new PropertyAccess(new Reference('a'), 'prop', PropertyMode::Assign, '@a');
	$completed = $access->complete($resolver);

	Assert::notSame($access, $completed);
	Assert::same('@a', $access->value); // original untouched
	Assert::type(Reference::class, $completed->value);
	Assert::same("\$this->getService('a')->prop = \$this->getService('a')", $completed->generateCode($generator));
});


test('complete() of a read leaves no value to resolve', function () {
	[$resolver, $generator] = harness();
	$completed = (new PropertyAccess(new Reference('a'), 'prop', PropertyMode::Read))->complete($resolver);
	Assert::same("\$this->getService('a')->prop", $completed->generateCode($generator));
});


testException('complete(): error in value is annotated with the property', function () {
	[$resolver] = harness();
	(new PropertyAccess(new Reference('a'), 'prop', PropertyMode::Assign, '@missing'))->complete($resolver);
}, DI\ServiceCreationException::class, "Reference to missing service 'missing'. (used in @a::\$prop)");


test('transformValues() maps target, name and value, original untouched', function () {
	$map = fn($v) => is_string($v) ? strtr($v, ['%name%' => 'prop', '%val%' => 'x']) : $v;

	$access = new PropertyAccess(new Reference('a'), '%name%', PropertyMode::Assign, '%val%');
	$transformed = $access->transformValues($map);
	Assert::same('prop', $transformed->name);
	Assert::same('x', $transformed->value);
	Assert::same(PropertyMode::Assign, $transformed->mode);
	Assert::same('%name%', $access->name); // original untouched

	$guarded = $access->transformValues(fn($v) => is_string($v) ? new Reference('x') : $v);
	Assert::same('%name%', $guarded->name); // non-string result for name is ignored
});


test('property Statements complete to PropertyAccess nodes with the right mode', function () {
	[$resolver, $generator] = harness();
	foreach ([
		[new Statement([new Reference('a'), '$prop'], [5]), PropertyMode::Assign, "\$this->getService('a')->prop = 5"],
		[new Statement([new Reference('a'), '$items[]'], [5]), PropertyMode::Append, "\$this->getService('a')->items[] = 5"],
		[new Statement([new Reference('a'), '$prop']), PropertyMode::Read, "\$this->getService('a')->prop"],
		[new Statement(['PropDep', '$prop'], [5]), PropertyMode::Assign, "'PropDep'::\$prop = 5"],
	] as [$statement, $mode, $expected]) {
		$completed = $statement->complete($resolver);
		Assert::type(PropertyAccess::class, $completed);
		Assert::same($mode, $completed->mode);
		Assert::same($expected, $completed->generateCode($generator));
	}
});


testException('append without a value is rejected', function () {
	[$resolver] = harness();
	(new Statement([new Reference('a'), '$items[]']))->complete($resolver);
}, DI\ServiceCreationException::class, 'Missing argument for $items[].');


testException('more than one value is rejected', function () {
	[$resolver] = harness();
	(new Statement([new Reference('a'), '$prop'], [1, 2]))->complete($resolver);
}, Nette\Utils\AssertionException::class, "The setup arguments for '%a%' expects to be list in range 0..1, array given.");
