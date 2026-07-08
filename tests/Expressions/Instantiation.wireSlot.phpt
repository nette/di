<?php declare(strict_types=1);

/**
 * Test: ArgumentPlaceholder::Single as an open autowiring slot in Instantiation/Call
 * (the DSL wire() without arguments). Mirrors the NEON `_` behaviour.
 */

use Nette\DI;
use Nette\DI\Expressions\ArgumentPlaceholder;
use Nette\DI\Expressions\Call;
use Nette\DI\Expressions\Instantiation;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class WireDep
{
}

class WireTarget
{
	public array $args;


	public function __construct(WireDep $dep, string $text = '', ?int $num = null)
	{
		$this->args = func_get_args();
	}


	public static function make(WireDep $dep, string $text = ''): self
	{
		return new self($dep, $text);
	}
}


function wireHarness(): array
{
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('dep')->setType(WireDep::class);
	return [new DI\Resolver($builder), new DI\PhpGenerator($builder)];
}


test('open slot at position 0 is autowired, later positional args keep position', function () {
	[$resolver, $generator] = wireHarness();
	$node = new Instantiation(WireTarget::class, [ArgumentPlaceholder::Single, 'hello']);
	$completed = $node->complete($resolver);

	// placeholder is gone; position 0 autowired to @dep, position 1 stays 'hello'
	Assert::type(Reference::class, $completed->arguments[0]);
	Assert::same('dep', $completed->arguments[0]->getValue());
	Assert::same('hello', $completed->arguments[1]);
	Assert::same("new WireTarget(\$this->getService('dep'), 'hello')", $completed->generateCode($generator));
});


test('trailing open slot with only autowirable params', function () {
	[$resolver, $generator] = wireHarness();
	$node = new Instantiation(WireTarget::class, [ArgumentPlaceholder::Single]);
	$completed = $node->complete($resolver);
	Assert::same("new WireTarget(\$this->getService('dep'))", $completed->generateCode($generator));
});


test('open slot works in a static Call as well', function () {
	[$resolver, $generator] = wireHarness();
	$node = new Call(WireTarget::class, 'make', [ArgumentPlaceholder::Single, 'x']);
	$completed = $node->complete($resolver);
	Assert::same("WireTarget::make(\$this->getService('dep'), 'x')", $completed->generateCode($generator));
});
