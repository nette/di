<?php declare(strict_types=1);

/**
 * Test: Expressions\Chaining - fluent call() / get() / set() / append() / constant() on object-valued nodes.
 */

use Nette\DI\Compiler\{PhpGenerator, Resolver};
use Nette\DI\Expressions\{
	Call, ConstantFetch, Expansion, Instantiation, PartialCall, PropertyAccess, PropertyMode, Reference, ServiceCollection, SpecialFunction,
};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class FluentUrl
{
	public function getHost(): string
	{
		return '';
	}
}

class FluentSvc
{
	public string $prop = '';

	/** @var string[] */
	public array $items = [];


	public function getUrl(): FluentUrl
	{
		return new FluentUrl;
	}
}


// call() builds a Call node whose target is the receiver
$expr = new Reference('a');
$call = $expr->call('foo', [1, 2]);
Assert::type(Call::class, $call);
Assert::same($expr, $call->target);
Assert::same('foo', $call->name);
Assert::same([1, 2], $call->arguments);


// call() without arguments
$call = new Reference('a')->call('foo');
Assert::same([], $call->arguments);


// named arguments via args()
$call = new Reference('a')->call('foo', ['x' => 1, 'y' => 2]);
Assert::same(['x' => 1, 'y' => 2], $call->arguments);


// get() builds a read PropertyAccess node
$prop = new Reference('a')->get('bar');
Assert::type(PropertyAccess::class, $prop);
Assert::same('bar', $prop->name);
Assert::same(PropertyMode::Read, $prop->mode);


// set() builds an assigning PropertyAccess node
$prop = new Reference('a')->set('bar', 123);
Assert::same(PropertyMode::Assign, $prop->mode);
Assert::same(123, $prop->value);


// set() assigns null as any other value
$prop = new Reference('a')->set('bar', null);
Assert::same(PropertyMode::Assign, $prop->mode);
Assert::null($prop->value);


// append() builds an appending PropertyAccess node
$prop = new Reference('a')->append('bar', 123);
Assert::same(PropertyMode::Append, $prop->mode);
Assert::same(123, $prop->value);


// constant() builds a ConstantFetch node
$const = new Reference('a')->constant('FOO');
Assert::type(ConstantFetch::class, $const);
Assert::same('FOO', $const->name);


// chaining: service('a')->call('b')->call('c')
$chain = new Reference('a')->call('b')->call('c');
Assert::type(Call::class, $chain);
Assert::same('c', $chain->name);
Assert::type(Call::class, $chain->target);
Assert::same('b', $chain->target->name);
Assert::type(Reference::class, $chain->target->target);


// chaining works on instantiation too (F7 equivalent: Foo()::b()::c())
$chain = new Instantiation(stdClass::class)->call('b')->call('c');
Assert::type(Instantiation::class, $chain->target->target);


// chaining lives only on Reference, Instantiation and Call, NOT on the Expression base
Assert::false(method_exists(Nette\DI\Expression::class, 'call'));
Assert::false(method_exists(SpecialFunction::class, 'call'));      // not()/casts -> scalar
Assert::false(method_exists(ServiceCollection::class, 'call'));    // typed()/tagged() -> array
Assert::false(method_exists(Expansion::class, 'call'));            // %param% -> often a string
Assert::false(method_exists(ConstantFetch::class, 'get'));         // constant -> value
Assert::false(method_exists(PropertyAccess::class, 'call'));       // may be an assignment command
Assert::false(method_exists(PartialCall::class, 'call'));          // chain after FCC is a NEON-only edge case


// generateCode of a chained method call on a service (= NEON @a::b()::c())
$builder = new Nette\DI\ContainerBuilder;
$builder->addDefinition('a')->setType(FluentSvc::class);
$resolver = new Resolver($builder);
$generator = new PhpGenerator($builder);

$expr = new Reference('a')->call('getUrl')->call('getHost');
$completed = $expr->complete($resolver);
Assert::same("\$this->getService('a')->getUrl()->getHost()", $completed->generateCode($generator));


// property read on a service (= NEON @a::$prop)
$expr = new Reference('a')->get('prop');
Assert::same("\$this->getService('a')->prop", $expr->complete($resolver)->generateCode($generator));


// property assignment and append on a service
$expr = new Reference('a')->set('prop', 'x');
Assert::same("\$this->getService('a')->prop = 'x'", $expr->complete($resolver)->generateCode($generator));

$expr = new Reference('a')->append('items', 'x');
Assert::same("\$this->getService('a')->items[] = 'x'", $expr->complete($resolver)->generateCode($generator));


// code() is chainable: continue a raw expression (e.g. a generated local variable)
$expr = new Nette\DI\Expressions\PhpCode('$baseUrl')->call('resolve', ['/x'])->call('getAbsoluteUrl');
$completed = $expr->complete($resolver);
Assert::same("\$baseUrl->resolve('/x')->getAbsoluteUrl()", $completed->generateCode($generator));


// property access on an expression other than a reference
$expr = new Nette\DI\Expressions\PhpCode('$baseUrl')->get('host');
Assert::same('$baseUrl->host', $expr->complete($resolver)->generateCode($generator));

$expr = new Instantiation(FluentSvc::class)->get('prop');
Assert::same('(new FluentSvc)->prop', $expr->complete($resolver)->generateCode($generator));

$expr = new Reference('a')->call('getUrl')->set('host', 'x');
Assert::same("\$this->getService('a')->getUrl()->host = 'x'", $expr->complete($resolver)->generateCode($generator));
