<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Expressions\PartialCall unit test.
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\ArgumentPlaceholder;
use Nette\DI\Expressions\PartialCall;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Subject
{
	public function pub()
	{
	}


	private function priv()
	{
	}
}


function harness(): array
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('a')->setType(Subject::class);
	return [new DI\Resolver($builder), new DI\PhpGenerator($builder)];
}


test('resolveType() is always Closure', function () {
	[$resolver] = harness();
	Assert::same(Closure::class, (new PartialCall(null, 'trim'))->resolveType($resolver));
	Assert::same(Closure::class, (new PartialCall(Subject::class, 'pub'))->resolveType($resolver));
});


test('generateCode(): all target forms', function () {
	[, $generator] = harness();
	Assert::same('trim(...)', (new PartialCall(null, 'trim'))->generateCode($generator));
	Assert::same('Foo\bar(...)', (new PartialCall(null, 'Foo\bar'))->generateCode($generator));
	Assert::same('Subject::pub(...)', (new PartialCall('Subject', 'pub'))->generateCode($generator));
	Assert::same("\$this->getService('a')->pub(...)", (new PartialCall(new Reference('a'), 'pub'))->generateCode($generator));
	Assert::same('$service->pub(...)', (new PartialCall(new Reference(Reference::Self), 'pub'))->generateCode($generator));
	Assert::same('(new Subject)->pub(...)', (new PartialCall(new Statement(Subject::class), 'pub'))->generateCode($generator));
});


test('generateCode(): partial application with bound arguments and placeholders (PHP 8.6+)', function () {
	[, $generator] = harness();
	// single ? placeholders and a bound value
	Assert::same("str_replace(?, ?, 'x')", (new PartialCall(null, 'str_replace', [ArgumentPlaceholder::Single, ArgumentPlaceholder::Single, 'x']))->generateCode($generator));
	// bound value then placeholder
	Assert::same('trim(1, ?)', (new PartialCall(null, 'trim', [1, ArgumentPlaceholder::Single]))->generateCode($generator));
	// placeholder then variadic rest
	Assert::same('Subject::pub(?, ...)', (new PartialCall('Subject', 'pub', [ArgumentPlaceholder::Single, ArgumentPlaceholder::Variadic]))->generateCode($generator));
	// named arguments
	Assert::same('trim(string: ?, characters: 1)', (new PartialCall(null, 'trim', ['string' => ArgumentPlaceholder::Single, 'characters' => 1]))->generateCode($generator));
	// placeholder on a method call
	Assert::same("\$this->getService('a')->pub(?)", (new PartialCall(new Reference('a'), 'pub', [ArgumentPlaceholder::Single]))->generateCode($generator));
});


test('complete(): bound @service arguments are resolved, placeholders pass through untouched', function () {
	[$resolver, $generator] = harness();
	$partial = new PartialCall(null, 'str_replace', ['@a', ArgumentPlaceholder::Single, ArgumentPlaceholder::Variadic]);
	$completed = $partial->complete($resolver);

	Assert::notSame($partial, $completed);
	Assert::same('@a', $partial->arguments[0]);                       // original untouched
	Assert::type(Reference::class, $completed->arguments[0]);         // @a resolved
	Assert::same(ArgumentPlaceholder::Single, $completed->arguments[1]);   // placeholder kept
	Assert::same(ArgumentPlaceholder::Variadic, $completed->arguments[2]); // placeholder kept
	Assert::same("str_replace(\$this->getService('a'), ?, ...)", $completed->generateCode($generator));
});


test('complete(): valid callables pass and stay unchanged', function () {
	[$resolver] = harness();
	foreach ([
		new PartialCall(null, 'trim'),
		new PartialCall(null, 'Foo\undefinedFunc'), // function existence is not verified (not autoloadable)
		new PartialCall(Subject::class, 'pub'),
		new PartialCall(Subject::class, 'magic'), // missing method tolerated because of __callStatic
	] as $callable) {
		Assert::equal($callable, $callable->complete($resolver));
	}
});


test('complete() of expression target returns a new instance, original is untouched', function () {
	[$resolver] = harness();
	$inner = new Reference('a');
	$callable = new PartialCall($inner, 'pub');
	$completed = $callable->complete($resolver);
	Assert::notSame($callable, $completed);
	Assert::same($inner, $callable->target);
});


testException('complete(): invalid class name', function () {
	[$resolver] = harness();
	(new PartialCall('Foo Bar', 'm'))->complete($resolver);
}, DI\ServiceCreationException::class, "Expected a valid class name, 'Foo Bar' given.");


testException('complete(): invalid method name', function () {
	[$resolver] = harness();
	(new PartialCall(Subject::class, 'foo bar'))->complete($resolver);
}, DI\ServiceCreationException::class, "Expected a valid method name, 'foo bar' given.");


testException('complete(): invalid function name', function () {
	[$resolver] = harness();
	(new PartialCall(null, 'foo bar'))->complete($resolver);
}, DI\ServiceCreationException::class, "Expected a valid function name, 'foo bar' given.");


testException('complete(): unknown class', function () {
	[$resolver] = harness();
	(new PartialCall('Unknown', 'm'))->complete($resolver);
}, DI\ServiceCreationException::class, "Class 'Unknown' not found.");


testException('complete(): non-public method', function () {
	[$resolver] = harness();
	(new PartialCall(Subject::class, 'priv'))->complete($resolver);
}, DI\ServiceCreationException::class, 'Subject::priv() is not callable.');


testException('complete(): reference to missing service inside target', function () {
	[$resolver] = harness();
	(new PartialCall(new Reference('missing'), 'm'))->complete($resolver);
}, DI\ServiceCreationException::class, "Reference to missing service 'missing'.");


testException('complete(): plain arguments without any placeholder are rejected', function () {
	[$resolver] = harness();
	(new PartialCall(null, 'trim', [1, 2]))->complete($resolver);
}, DI\ServiceCreationException::class, 'First-class callable trim() must contain at least one placeholder (? or ...).');


test('transformValues(): callback is applied to target and name, original is untouched', function () {
	$callable = new PartialCall('%class%', '%method%');
	$transformed = $callable->transformValues(fn($v) => is_string($v) ? strtr($v, ['%class%' => 'Subject', '%method%' => 'pub']) : $v);
	Assert::notSame($callable, $transformed);
	Assert::same('Subject', $transformed->target);
	Assert::same('pub', $transformed->name);
	Assert::same('%class%', $callable->target);
	Assert::same('%method%', $callable->name);
});


test('transformValues(): non-string result for name keeps the original name', function () {
	$callable = new PartialCall(new Reference('a'), '@weird');
	$transformed = $callable->transformValues(
		fn($v) => is_string($v) && str_starts_with($v, '@') ? new Reference(substr($v, 1)) : $v,
	);
	Assert::same('@weird', $transformed->name); // not replaced by Reference
});
