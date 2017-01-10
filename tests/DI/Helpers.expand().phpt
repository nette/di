<?php

/**
 * Test: Nette\DI\Helpers::expand()
 */

declare(strict_types=1);

use Nette\DI\Helpers;
use Nette\PhpGenerator\PhpLiteral;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same('item', Helpers::expand('item', []));
Assert::same(123, Helpers::expand(123, []));
Assert::same('%', Helpers::expand('%%', []));
Assert::same('item', Helpers::expand('%key%', ['key' => 'item']));
Assert::same(123, Helpers::expand('%key%', ['key' => 123]));
Assert::same('a123b123c', Helpers::expand('a%key%b%key%c', ['key' => 123]));
Assert::same(123, Helpers::expand('%key1.key2%', ['key1' => ['key2' => 123]]));
Assert::same(123, Helpers::expand('%key1%', ['key1' => '%key2%', 'key2' => 123], TRUE));
Assert::same([123], Helpers::expand(['%key1%'], ['key1' => '%key2%', 'key2' => 123], TRUE));
Assert::same(
	['key1' => 123, 'key2' => 'abc'],
	Helpers::expand('%keyA%', [
		'keyA' => ['key1' => 123, 'key2' => '%keyB%'],
		'keyB' => 'abc',
	], TRUE)
);

Assert::equal(new PhpLiteral('func()'), Helpers::expand('%key%', ['key' => new PhpLiteral('func()')]));
Assert::equal(new PhpLiteral("'text' . (func())"), Helpers::expand('text%key%', ['key' => new PhpLiteral('func()')]));
Assert::equal(new PhpLiteral("(func()) . 'text'"), Helpers::expand('%key%text', ['key' => new PhpLiteral('func()')]));
Assert::equal(new PhpLiteral("'a' . (func()) . 'b' . '123' . (func()) . 'c'"), Helpers::expand('a%key1%b%key2%%key1%c', ['key1' => new PhpLiteral('func()'), 'key2' => 123]));


Assert::exception(function () {
	Helpers::expand('%missing%', []);
}, Nette\InvalidArgumentException::class, "Missing parameter 'missing'.");

Assert::exception(function () {
	Helpers::expand('%key1%a', ['key1' => ['key2' => 123]]);
}, Nette\InvalidArgumentException::class, "Unable to concatenate non-scalar parameter 'key1' into '%key1%a'.");

Assert::exception(function () {
	Helpers::expand('%key1%', ['key1' => '%key2%', 'key2' => '%key1%'], TRUE);
}, Nette\InvalidArgumentException::class, 'Circular reference detected for variables: key1, key2.');
