<?php declare(strict_types=1);

/**
 * Test: characterizes the REAL v3.3 behaviour of special Statement forms used as a service
 * creator and of Resolver::resolveEntityType() (the ancestor of the planned Expression::resolveType()).
 */

use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\Reference;
use Nette\DI\PhpGenerator;
use Nette\DI\Resolver;
use Nette\DI\ServiceCreationException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Holder
{
	public stdClass $obj;


	public function __construct()
	{
		$this->obj = new stdClass;
	}
}


/** @return array{ContainerBuilder, Resolver, PhpGenerator} */
function harness(): array
{
	$builder = new ContainerBuilder;
	$builder->addDefinition('svc')->setType(Holder::class);
	return [$builder, new Resolver($builder), new PhpGenerator($builder)];
}


/** compiles the creator into the body of createServiceX() */
function creatorCode(Statement $creator, ?string $type): string
{
	[$builder, , $generator] = harness();
	$def = $builder->addDefinition('x')->setFactory($creator);
	if ($type !== null) {
		$def->setType($type);
	}

	$builder->complete();
	$code = $generator->toString($generator->generate('Container'));
	preg_match('#function createServiceX\(\).*?\{\s*(.*?)\s*\}#s', $code, $m);
	return trim($m[1]);
}


// --- resolveEntityType() cannot infer a type for any of these forms ---------------------------

test('resolveEntityType(): special functions are treated as class names', function () {
	[, $resolver] = harness();
	Assert::exception(fn() => $resolver->resolveEntityType(new Statement('not', [true])), ServiceCreationException::class, "Class 'not' not found.");
	Assert::exception(fn() => $resolver->resolveEntityType(new Statement('int', ['5'])), ServiceCreationException::class, "Class 'int' not found.");
});


test('resolveEntityType(): typed()/tagged() are treated as class names', function () {
	[, $resolver] = harness();
	Assert::exception(fn() => $resolver->resolveEntityType(new Statement('typed', [stdClass::class])), ServiceCreationException::class, "Class 'typed' not found.");
	Assert::exception(fn() => $resolver->resolveEntityType(new Statement('tagged', ['t'])), ServiceCreationException::class, "Class 'tagged' not found.");
});


test('resolveEntityType(): property access has no type reflection (read and append)', function () {
	[, $resolver] = harness();
	// the declared property type is never reflected; assigned/appended arguments are irrelevant to resolution,
	// so the read and write forms share one entity - only the append notation $prop[] differs
	Assert::exception(fn() => $resolver->resolveEntityType(new Statement([new Reference('svc'), '$obj'])), ServiceCreationException::class, 'Method Holder::$obj() is not callable.');
	Assert::exception(fn() => $resolver->resolveEntityType(new Statement([new Reference('svc'), '$obj[]'], [1])), ServiceCreationException::class, 'Method Holder::$obj[]() is not callable.');
});


test('resolveEntityType(): a PHP literal is treated as a class name', function () {
	[, $resolver] = harness();
	Assert::exception(fn() => $resolver->resolveEntityType(new Statement('new ReflectionClass(?)', [stdClass::class])), ServiceCreationException::class, "Class 'new ReflectionClass(?)' not found.");
});


// --- practical consequence: usable as a creator only WITH an explicit type: --------------------

test('special function creator: generates code with a type, fails without one', function () {
	Assert::same('return !(true);', creatorCode(new Statement('not', [true]), stdClass::class));
	Assert::exception(fn() => creatorCode(new Statement('not', [true]), null), ServiceCreationException::class, "%A?%Class 'not' not found.");
});


test('typed()/tagged() cannot be used as a creator at all (even with a type)', function () {
	Assert::exception(fn() => creatorCode(new Statement('typed', [stdClass::class]), stdClass::class), ServiceCreationException::class, "%A?%Class 'typed' not found.");
	Assert::exception(fn() => creatorCode(new Statement('tagged', ['t']), stdClass::class), ServiceCreationException::class, "%A?%Class 'tagged' not found.");
});


test('property read creator: generates code with a type, fails without one', function () {
	Assert::same("return \$this->getService('svc')->obj;", creatorCode(new Statement([new Reference('svc'), '$obj']), stdClass::class));
	Assert::exception(fn() => creatorCode(new Statement([new Reference('svc'), '$obj']), null), ServiceCreationException::class, '%A?%Method Holder::$obj() is not callable.');
});


test('PHP literal creator: generates code with a type, fails without one', function () {
	// a PhpLiteral used as a creator must evaluate to an object (a service is an object)
	Assert::same("return new ReflectionClass('stdClass');", creatorCode(new Statement('new ReflectionClass(?)', [stdClass::class]), ReflectionClass::class));
	Assert::exception(fn() => creatorCode(new Statement('new ReflectionClass(?)', [stdClass::class]), null), ServiceCreationException::class, "%A?%Class 'new ReflectionClass(?)' not found.");
});


// --- same behaviour through the NEON config entry point (Compiler + NeonAdapter) ---------------
// (a PHP literal has no NEON notation - it is an API-only form - so it is absent here)

test('NEON: special function as a creator needs an explicit type', function () {
	Assert::exception(
		fn() => createContainer(new Compiler, "services:\n\tx: not(true)"),
		ServiceCreationException::class,
		"%A?%Class 'not' not found.",
	);
	Assert::type(Container::class, createContainer(new Compiler, "services:\n\tx:\n\t\tcreate: not(true)\n\t\ttype: stdClass"));
});


test('NEON: typed()/tagged() cannot be a creator, even with a type', function () {
	Assert::exception(
		fn() => createContainer(new Compiler, "services:\n\tx:\n\t\tcreate: typed(stdClass)\n\t\ttype: stdClass"),
		ServiceCreationException::class,
		"%A?%Class 'typed' not found.",
	);
	Assert::exception(
		fn() => createContainer(new Compiler, "services:\n\tx:\n\t\tcreate: tagged(t)\n\t\ttype: stdClass"),
		ServiceCreationException::class,
		"%A?%Class 'tagged' not found.",
	);
});


test('NEON: property read as a creator needs an explicit type, then works at runtime', function () {
	Assert::exception(
		fn() => createContainer(new Compiler, "services:\n\tsvc: Holder\n\tx: @svc::\$obj"),
		ServiceCreationException::class,
		'%A?%Method Holder::$obj() is not callable.',
	);
	$container = createContainer(new Compiler, "services:\n\tsvc: Holder\n\tx:\n\t\tcreate: @svc::\$obj\n\t\ttype: stdClass");
	Assert::type(stdClass::class, $container->getService('x'));
});
