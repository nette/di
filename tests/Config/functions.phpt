<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Config element functions (service, wire, create, param, ...).
 * They are pure, context-free factories for Expression nodes.
 */

namespace Nette\DI;

use Nette\DI\Expressions\ArgumentPlaceholder;
use Nette\DI\Expressions\Call;
use Nette\DI\Expressions\Expansion;
use Nette\DI\Expressions\Instantiation;
use Nette\DI\Expressions\PhpCode;
use Nette\DI\Expressions\Reference;
use Nette\DI\Expressions\ServiceCollection;
use Nette\DI\Expressions\SpecialFunction;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Foo
{
	public static function make(): self
	{
		return new self;
	}
}


test('service(): name vs type by backslash', function () {
	$ref = service('logger');
	Assert::type(Reference::class, $ref);
	Assert::true($ref->isName());

	$ref = service('App\Model');
	Assert::true($ref->isType());
	Assert::same('App\Model', $ref->getValue());
});


test('wire(): type reference', function () {
	$ref = wire(\PDO::class);
	Assert::type(Reference::class, $ref);
	Assert::true($ref->isType());
	Assert::same('\PDO', $ref->getValue()); // fromType prepends backslash for root-namespace class
});


test('_ : autowire-position placeholder constant', function () {
	Assert::same(ArgumentPlaceholder::Single, _);
});


test('self()', function () {
	Assert::true(self()->isSelf());
});


test('param() and expand() build Expansion templates', function () {
	Assert::equal(new Expansion('%tempDir%'), param('tempDir'));
	Assert::equal(new Expansion('%tempDir%/cache'), expand('%tempDir%/cache'));
});


test('create(): instantiation only; args as an array, named args via args()', function () {
	$node = create(Foo::class, [1, 2]);
	Assert::type(Instantiation::class, $node);
	Assert::same(Foo::class, $node->class);
	Assert::same([1, 2], $node->arguments);

	// args() keeps the name: syntax
	$node = create(Foo::class, args(x: 1, y: 2));
	Assert::same(['x' => 1, 'y' => 2], $node->arguments);

	// the _ placeholder marks an autowired position
	$node = create(Foo::class, [_, 'scalar']);
	Assert::same([ArgumentPlaceholder::Single, 'scalar'], $node->arguments);
});


test('create(): a factory method is rejected - use call()', function () {
	Assert::exception(fn() => create('Foo::make'), \InvalidArgumentException::class, '%a%use call(%a%');
});


test('call(): global function, static method, array and FCC forms', function () {
	$node = call('trim');
	Assert::type(Call::class, $node);
	Assert::null($node->target);
	Assert::same('trim', $node->name);

	$node = call('Foo::make', [1]);
	Assert::same('Foo', $node->target);
	Assert::same([1], $node->arguments);

	$node = call([Foo::class, 'make']);
	Assert::same(Foo::class, $node->target);
	Assert::same('make', $node->name);

	$node = call(Foo::make(...)); // first-class callable
	Assert::same(Foo::class, $node->target);
	Assert::same('make', $node->name);
});


test('services(): typed and tagged collections', function () {
	$node = services(type: Foo::class);
	Assert::type(ServiceCollection::class, $node);
	Assert::same([Foo::class], $node->types);
	Assert::same([], $node->tags);

	$node = services(tag: 'logger');
	Assert::same(['logger'], $node->tags);

	// type and tag together -> intersection collection
	$node = services(type: Foo::class, tag: 'x');
	Assert::same([Foo::class], $node->types);
	Assert::same(['x'], $node->tags);

	Assert::exception(fn() => services(), \InvalidArgumentException::class);
});


test('cast(), not(), code()', function () {
	Assert::equal(new SpecialFunction('int', [5]), cast('int', 5));
	Assert::equal(new SpecialFunction('not', [true]), not(true));
	Assert::equal(new PhpCode('trim(?)', ['x']), code('trim(?)', ['x']));
});


test('strings stay literal: no @ / % decoding', function () {
	// @service and %param% passed as plain strings must survive verbatim
	$node = create(Foo::class, ['@notAReference', '%notAParam%', '@@x', '%%y']);
	Assert::same(['@notAReference', '%notAParam%', '@@x', '%%y'], $node->arguments);
});


test('serviceFrom(): a @service string or a Reference, else null', function () {
	$ref = serviceFrom('@logger');
	Assert::type(Reference::class, $ref);
	Assert::true($ref->isName());
	Assert::same('logger', $ref->getValue());

	Assert::true(serviceFrom('@\PDO')->isType());   // @\Type -> reference by type

	// already a Reference -> passed through
	$r = service('x');
	Assert::same($r, serviceFrom($r));

	// not a reference -> null (so ?? falls through)
	Assert::null(serviceFrom('@@x'));                // escaped, not a reference
	Assert::null(serviceFrom(Foo::class));           // a class name
	Assert::null(serviceFrom('literal'));
	Assert::null(serviceFrom(42));
});


test('classFrom(): a valid class name (validity, not existence) or an Instantiation, else null', function () {
	$node = classFrom(Foo::class);
	Assert::type(Instantiation::class, $node);
	Assert::same(Foo::class, $node->class);

	// validity, not existence: well-formed names are recognized even if not loaded, incl. bare names
	Assert::type(Instantiation::class, classFrom('App\NotLoadedYet'));
	Assert::type(Instantiation::class, classFrom('MyExtension'));

	// already an Instantiation -> passed through
	$inst = create(Foo::class);
	Assert::same($inst, classFrom($inst));

	// not a valid class name -> null (so ?? falls through)
	Assert::null(classFrom('/some/path'));           // slash
	Assert::null(classFrom('has space'));
	Assert::null(classFrom('@logger'));
	Assert::null(classFrom(42));
});


test('classFrom(qualified: true): for ambiguous slots where a bare word means a path/preset, not a class', function () {
	// only a backslash-qualified name is taken as a class
	Assert::type(Instantiation::class, classFrom('App\Mapper', qualified: true));
	Assert::null(classFrom('theme1', qualified: true));  // bare word -> falls through (a path)

	// leading backslash forces class interpretation of a bare name
	$node = classFrom('\MyMapper', qualified: true);
	Assert::type(Instantiation::class, $node);
	Assert::same('\MyMapper', $node->class);

	// an Instantiation is passed through regardless
	$inst = create(Foo::class);
	Assert::same($inst, classFrom($inst, qualified: true));

	// deterministic: classification never depends on what is loaded
	Assert::type(Instantiation::class, classFrom('App\NotLoadedYet', qualified: true));
});


test('composition: the call-site declares accepted forms and default via ??', function () {
	// @service, else class, else literal default (literal must not look like a class name)
	Assert::type(Reference::class, serviceFrom('@x') ?? classFrom('@x') ?? '@x');
	Assert::type(Instantiation::class, serviceFrom(Foo::class) ?? classFrom(Foo::class) ?? 'x');
	Assert::same('/path', serviceFrom('/path') ?? classFrom('/path') ?? '/path');

	// neither @service nor a valid class name -> throw (extensions: slot)
	Assert::exception(
		fn() => serviceFrom('bad value!') ?? classFrom('bad value!') ?? throw new InvalidConfigurationException('nope'),
		InvalidConfigurationException::class,
	);
});
