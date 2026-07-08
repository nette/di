<?php declare(strict_types=1);

/**
 * Test: how the special Expression forms resolve their type and behave as a service creator.
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


// --- resolveType() ----------------------------------------------------------------------------

test('resolveType(): special functions and a PHP literal are not resolvable (null)', function () {
	[, $resolver] = harness();
	Assert::null($resolver->resolveEntityType(new Statement('not', [true])));
	Assert::null($resolver->resolveEntityType(new Statement('int', ['5'])));
	Assert::null($resolver->resolveEntityType(new Statement('new ReflectionClass(?)', [stdClass::class])));
});


test('resolveType(): typed()/tagged() are still treated as class names', function () {
	[, $resolver] = harness();
	Assert::exception(fn() => (new Statement('typed', [stdClass::class]))->resolveType($resolver), ServiceCreationException::class, "Class 'typed' not found.");
	Assert::exception(fn() => (new Statement('tagged', ['t']))->resolveType($resolver), ServiceCreationException::class, "Class 'tagged' not found.");
});


test('resolveEntityType(): a property read reflects the declared type', function () {
	[, $resolver] = harness();
	Assert::same(stdClass::class, $resolver->resolveEntityType(new Statement([new Reference('svc'), '$obj'])));
});


test('resolveType(): a property write reflects the assigned value; scalars and appends are null', function () {
	[, $resolver] = harness();
	Assert::same(stdClass::class, (new Statement([new Reference('svc'), '$obj'], [new Statement(stdClass::class)]))->resolveType($resolver));
	Assert::null((new Statement([new Reference('svc'), '$obj'], [5]))->resolveType($resolver));
	Assert::null((new Statement([new Reference('svc'), '$obj[]'], [1]))->resolveType($resolver));
});


// --- as a service creator ---------------------------------------------------------------------

test('special function creator: needs an explicit type; generated code is unchanged', function () {
	Assert::same('return !(true);', creatorCode(new Statement('not', [true]), stdClass::class));
	Assert::exception(fn() => creatorCode(new Statement('not', [true]), null), ServiceCreationException::class, '%A?%Unknown service type, specify it%A?%');
});


test('typed()/tagged() cannot be used as a creator (even with a type)', function () {
	Assert::exception(fn() => creatorCode(new Statement('typed', [stdClass::class]), stdClass::class), ServiceCreationException::class, "%A?%Class 'typed' not found.");
	Assert::exception(fn() => creatorCode(new Statement('tagged', ['t']), stdClass::class), ServiceCreationException::class, "%A?%Class 'tagged' not found.");
});


test('property read creator: works WITHOUT a type thanks to reflection', function () {
	$expected = "return \$this->getService('svc')->obj;";
	Assert::same($expected, creatorCode(new Statement([new Reference('svc'), '$obj']), null));
	Assert::same($expected, creatorCode(new Statement([new Reference('svc'), '$obj']), stdClass::class));
});


test('PHP literal creator: needs an explicit type (a literal must evaluate to an object)', function () {
	Assert::same("return new ReflectionClass('stdClass');", creatorCode(new Statement('new ReflectionClass(?)', [stdClass::class]), ReflectionClass::class));
	Assert::exception(fn() => creatorCode(new Statement('new ReflectionClass(?)', [stdClass::class]), null), ServiceCreationException::class, '%A?%Unknown service type, specify it%A?%');
});


// --- same behaviour through the NEON config entry point ---------------------------------------
// (a PHP literal has no NEON notation - it is an API-only form - so it is absent here)

test('NEON: a special function as a creator needs an explicit type', function () {
	Assert::exception(
		fn() => createContainer(new Compiler, "services:\n\tx: not(true)"),
		ServiceCreationException::class,
		'%A?%Unknown service type, specify it%A?%',
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


test('NEON: a property read as a creator works without a type and returns the object', function () {
	$container = createContainer(new Compiler, "services:\n\tsvc: Holder\n\tx: @svc::\$obj");
	Assert::type(stdClass::class, $container->getService('x'));
});
