<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Expressions\Expansion - deferred %parameter% expansion (the object form of
 * param()/expand()). complete() runs Helpers::expand against the container parameters.
 */

use Nette\DI;
use Nette\DI\Expressions\Expansion;
use Nette\DI\Expressions\PhpCode;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function harness(array $params): array
{
	$builder = new DI\ContainerBuilder;
	$builder->parameters = $params;
	return [new DI\Resolver($builder), new DI\PhpGenerator($builder)];
}


test('whole-value parameter (param(\'dsn\') -> Expansion(\'%dsn%\'))', function () {
	[$resolver, $generator] = harness(['dsn' => 'mysql:host=localhost']);
	$completed = (new Expansion('%dsn%'))->complete($resolver);
	Assert::type(PhpCode::class, $completed);
	Assert::same("'mysql:host=localhost'", $completed->generateCode($generator));
});


test('interpolation into a string (expand(\'%dir%/cache\'))', function () {
	[$resolver, $generator] = harness(['dir' => '/var']);
	$completed = (new Expansion('%dir%/cache'))->complete($resolver);
	Assert::same("'/var/cache'", $completed->generateCode($generator));
});


test('a whole-value parameter may be an array', function () {
	[$resolver, $generator] = harness(['list' => [1, 2]]);
	$completed = (new Expansion('%list%'))->complete($resolver);
	Assert::same('[1, 2]', $completed->generateCode($generator));
});


test('nested parameter is expanded recursively', function () {
	[$resolver, $generator] = harness(['host' => 'localhost', 'dsn' => 'mysql:host=%host%']);
	$completed = (new Expansion('%dsn%'))->complete($resolver);
	Assert::same("'mysql:host=localhost'", $completed->generateCode($generator));
});


testException('missing parameter throws', function () {
	[$resolver] = harness([]);
	(new Expansion('%nope%'))->complete($resolver);
}, Nette\InvalidArgumentException::class, "Missing parameter 'nope'.");


testException('generateCode() before complete() throws', function () {
	[, $generator] = harness([]);
	(new Expansion('%dsn%'))->generateCode($generator);
}, LogicException::class);


test('transformValues() maps the template, original untouched', function () {
	$node = new Expansion('%a%');
	$transformed = $node->transformValues(fn($v) => is_string($v) ? '%b%' : $v);
	Assert::same('%b%', $transformed->template);
	Assert::same('%a%', $node->template);
});
