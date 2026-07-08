<?php declare(strict_types=1);

/**
 * Test: Expressions\Chaining - fluent method() / property() / constant() on object-valued nodes.
 */

use Nette\DI\Expressions\Call;
use Nette\DI\Expressions\ConstantFetch;
use Nette\DI\Expressions\Expansion;
use Nette\DI\Expressions\Instantiation;
use Nette\DI\Expressions\PartialCall;
use Nette\DI\Expressions\PropertyAccess;
use Nette\DI\Expressions\PropertyMode;
use Nette\DI\Expressions\Reference;
use Nette\DI\Expressions\ServiceCollection;
use Nette\DI\Expressions\SpecialFunction;
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


	public function getUrl(): FluentUrl
	{
		return new FluentUrl;
	}
}


// method() builds a Call node whose target is the receiver
$expr = new Reference('a');
$call = $expr->method('foo', [1, 2]);
Assert::type(Call::class, $call);
Assert::same($expr, $call->target);
Assert::same('foo', $call->name);
Assert::same([1, 2], $call->arguments);


// method() without arguments
$call = (new Reference('a'))->method('foo');
Assert::same([], $call->arguments);


// named arguments via args()
$call = (new Reference('a'))->method('foo', ['x' => 1, 'y' => 2]);
Assert::same(['x' => 1, 'y' => 2], $call->arguments);


// property() builds a read PropertyAccess node
$prop = (new Reference('a'))->property('bar');
Assert::type(PropertyAccess::class, $prop);
Assert::same('bar', $prop->name);
Assert::same(PropertyMode::Read, $prop->mode);


// constant() builds a ConstantFetch node
$const = (new Reference('a'))->constant('FOO');
Assert::type(ConstantFetch::class, $const);
Assert::same('FOO', $const->name);


// chaining: service('a')->method('b')->method('c')
$chain = (new Reference('a'))->method('b')->method('c');
Assert::type(Call::class, $chain);
Assert::same('c', $chain->name);
Assert::type(Call::class, $chain->target);
Assert::same('b', $chain->target->name);
Assert::type(Reference::class, $chain->target->target);


// chaining works on instantiation too (F7 equivalent: Foo()::b()::c())
$chain = (new Instantiation(stdClass::class))->method('b')->method('c');
Assert::type(Instantiation::class, $chain->target->target);


// chaining lives only on Reference, Instantiation and Call, NOT on the Expression base
Assert::false(method_exists(Nette\DI\Expression::class, 'method'));
Assert::false(method_exists(SpecialFunction::class, 'method'));      // not()/casts -> scalar
Assert::false(method_exists(ServiceCollection::class, 'method'));    // typed()/tagged() -> array
Assert::false(method_exists(Expansion::class, 'method'));            // %param% -> often a string
Assert::false(method_exists(ConstantFetch::class, 'property'));      // constant -> value
Assert::false(method_exists(PropertyAccess::class, 'method'));       // may be an assignment command
Assert::false(method_exists(PartialCall::class, 'method'));          // chain after FCC is a NEON-only edge case


// generateCode of a chained method call on a service (= NEON @a::b()::c())
$builder = new Nette\DI\ContainerBuilder;
$builder->addDefinition('a')->setType(FluentSvc::class);
$resolver = new Nette\DI\Resolver($builder);
$generator = new Nette\DI\PhpGenerator($builder);

$expr = (new Reference('a'))->method('getUrl')->method('getHost');
$completed = $expr->complete($resolver);
Assert::same("\$this->getService('a')->getUrl()->getHost()", $completed->generateCode($generator));


// property read on a service (= NEON @a::$prop)
$expr = (new Reference('a'))->property('prop');
Assert::same("\$this->getService('a')->prop", $expr->complete($resolver)->generateCode($generator));


// code() is chainable: continue a raw expression (e.g. a generated local variable)
$expr = (new Nette\DI\Expressions\PhpCode('$baseUrl'))->method('resolve', ['/x'])->method('getAbsoluteUrl');
$completed = $expr->complete($resolver);
Assert::same("\$baseUrl->resolve('/x')->getAbsoluteUrl()", $completed->generateCode($generator));
