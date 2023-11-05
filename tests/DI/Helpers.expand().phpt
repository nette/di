<?php

/**
 * Test: Nette\DI\Helpers::expand()
 */

declare(strict_types=1);

use Nette\DI\Definitions\Statement;
use Nette\DI\DynamicParameter;
use Nette\DI\Helpers;
use Nette\PhpGenerator\Literal;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same('item', Helpers::expand('item', []));
Assert::same(123, Helpers::expand(123, []));
Assert::same('%', Helpers::expand('%%', []));
Assert::same('item', Helpers::expand('%key%', ['key' => 'item']));
Assert::same(123, Helpers::expand('%key%', ['key' => 123]));
Assert::same('a123b123c', Helpers::expand('a%key%b%key%c', ['key' => 123]));
Assert::same(123, Helpers::expand('%key1.key2%', ['key1' => ['key2' => 123]]));
Assert::same('%key2%', Helpers::expand('%key1%', ['key1' => '%key2%', 'key2' => 123]));
Assert::same(123, Helpers::expand('%key1%', ['key1' => '%key2%', 'key2' => 123], true));
Assert::same([123], Helpers::expand(['%key1%'], ['key1' => '%key2%', 'key2' => 123], true));
Assert::same(['hello' => 123], Helpers::expand(['%key1%' => '%key2%'], ['key1' => 'hello', 'key2' => 123], true));
Assert::same(
	['key1' => 123, 'key2' => 'abc'],
	Helpers::expand('%keyA%', [
		'keyA' => ['key1' => 123, 'key2' => '%keyB%'],
		'keyB' => 'abc',
	], recursive: true),
);
Assert::same( // no double expand
	'%foo%',
	Helpers::expand('%string%', ['string' => '%%foo%'], true),
);
Assert::same( // no double expand
	'%foo%',
	Helpers::expand('%ref.a%', [
		'ref' => '%array%',
		'array' => ['a' => '%%foo%'],
	], true),
);

Assert::equal(
	new Literal('func()'),
	Helpers::expand('%key%', ['key' => new Literal('func()')]),
);

Assert::equal(
	new DynamicParameter("func()['foo']"),
	Helpers::expand('%key.foo%', ['key' => new DynamicParameter('func()')]),
);
Assert::equal(
	new Statement('::implode', [['text', new DynamicParameter('func()'), '']]),
	Helpers::expand('text%key%', ['key' => new DynamicParameter('func()')]),
);


Assert::exception(
	fn() => Helpers::expand('%missing%', []),
	Nette\InvalidArgumentException::class,
	"Missing parameter 'missing'.",
);

Assert::exception(
	fn() => Helpers::expand('%key1%a', ['key1' => ['key2' => 123]]),
	Nette\InvalidArgumentException::class,
	"Unable to concatenate non-scalar parameter 'key1' into '%key1%a'.",
);

Assert::exception(
	fn() => Helpers::expand('%key1%', ['key1' => '%key2%', 'key2' => '%key1%'], true),
	Nette\InvalidArgumentException::class,
	'Circular reference detected for parameters: %key1%, %key2%',
);

Assert::exception(
	fn() => Helpers::expand('%exp%', [
		'array' => ['a' => '%array%'],
		'exp' => '%array.a%',
	], true),
	Nette\InvalidArgumentException::class,
	'Circular reference detected for parameters: %exp%, %array.a%, %array%',
);


Assert::same(
	['key1' => 'hello', 'key2' => '*%key1%*'],
	@Helpers::expand('%parameters%', ['key1' => 'hello', 'key2' => '*%key1%*']), // deprecated
);
Assert::same(
	['key1' => 'hello', 'key2' => '*hello*'],
	@Helpers::expand('%parameters%', ['key1' => 'hello', 'key2' => '*%key1%*'], recursive: true), // deprecated
);
Assert::same(
	'own',
	@Helpers::expand('%parameters%', ['key1' => 'hello', 'key2' => '*%key1%*', 'parameters' => 'own']), // deprecated
);
