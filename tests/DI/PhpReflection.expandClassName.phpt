<?php

/**
 * Test: Expanding class alias to FQN.
 */

use Nette\DI\PhpReflection;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


require __DIR__ . '/files/expandClass.noNamespace.php';
require __DIR__ . '/files/expandClass.inBracketedNamespace.php';
require __DIR__ . '/files/expandClass.inNamespace.php';

$rcTest = new \ReflectionClass(Test::class);
$rcBTest = new \ReflectionClass(BTest::class);
$rcFoo = new \ReflectionClass(Test\Space\Foo::class);
$rcBar = new \ReflectionClass(Test\Space\Bar::class);


Assert::exception(function () use ($rcTest) {
	PhpReflection::expandClassName('', $rcTest);
}, Nette\InvalidArgumentException::class, 'Class name must not be empty.');


Assert::same('A', PhpReflection::expandClassName('A', $rcTest));
Assert::same('A\B', PhpReflection::expandClassName('C', $rcTest));

Assert::same('BTest', PhpReflection::expandClassName('BTest', $rcBTest));

Assert::same('Test\Space\Foo', PhpReflection::expandClassName('self', $rcFoo));
Assert::same('Test\Space\Foo', PhpReflection::expandClassName('Self', $rcFoo));
Assert::same('Test\Space\Foo', PhpReflection::expandClassName('static', $rcFoo));
Assert::same('Test\Space\Foo', PhpReflection::expandClassName('$this', $rcFoo));

foreach (['String', 'string', 'int', 'float', 'bool', 'array', 'callable'] as $type) {
	Assert::same(strtolower($type), PhpReflection::expandClassName($type, $rcFoo));
}

/*
alias to expand => [
	FQN for $rcFoo,
	FQN for $rcBar
]
*/
$cases = [
	'\Absolute' => [
		'Absolute',
		'Absolute',
	],
	'\Absolute\Foo' => [
		'Absolute\Foo',
		'Absolute\Foo',
	],

	'AAA' => [
		'Test\Space\AAA',
		'AAA',
	],
	'AAA\Foo' => [
		'Test\Space\AAA\Foo',
		'AAA\Foo',
	],

	'B' => [
		'Test\Space\B',
		'BBB',
	],
	'B\Foo' => [
		'Test\Space\B\Foo',
		'BBB\Foo',
	],

	'DDD' => [
		'Test\Space\DDD',
		'CCC\DDD',
	],
	'DDD\Foo' => [
		'Test\Space\DDD\Foo',
		'CCC\DDD\Foo',
	],

	'F' => [
		'Test\Space\F',
		'EEE\FFF',
	],
	'F\Foo' => [
		'Test\Space\F\Foo',
		'EEE\FFF\Foo',
	],

	'HHH' => [
		'Test\Space\HHH',
		'Test\Space\HHH',
	],

	'Notdef' => [
		'Test\Space\Notdef',
		'Test\Space\Notdef',
	],
	'Notdef\Foo' => [
		'Test\Space\Notdef\Foo',
		'Test\Space\Notdef\Foo',
	],

	// trim leading backslash
	'G' => [
		'Test\Space\G',
		'GGG',
	],
	'G\Foo' => [
		'Test\Space\G\Foo',
		'GGG\Foo',
	],
];
foreach ($cases as $alias => $fqn) {
	Assert::same($fqn[0], PhpReflection::expandClassName($alias, $rcFoo));
	Assert::same($fqn[1], PhpReflection::expandClassName($alias, $rcBar));
}


Assert::same(
	['C' => 'A\B'],
	PhpReflection::getUseStatements(new ReflectionClass('Test'))
);

Assert::same(
	[],
	PhpReflection::getUseStatements(new ReflectionClass('Test\Space\Foo'))
);

Assert::same(
	['AAA' => 'AAA', 'B' => 'BBB', 'DDD' => 'CCC\DDD', 'F' => 'EEE\FFF', 'G' => 'GGG'],
	PhpReflection::getUseStatements(new ReflectionClass('Test\Space\Bar'))
);
Assert::same(
	[],
	PhpReflection::getUseStatements(new ReflectionClass('stdClass'))
);
