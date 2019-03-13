<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Nette\DI\InvalidConfigurationException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // without default value
	$expectation = Expect::array();

	Assert::same([], $expectation->complete([]));

	Assert::same([1, 2, 3], $expectation->complete([1, 2, 3]));

	Assert::same(['key' => 'val'], $expectation->complete(['key' => 'val']));

	Assert::exception(function () use ($expectation) {
		$expectation->complete('one');
	}, Nette\DI\InvalidConfigurationException::class, "The option expects to be array, string 'one' given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(true);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be array, bool given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete(123);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be array, int 123 given.');

	Assert::same([], $expectation->complete(null));
});


test(function () { // merging
	$expectation = Expect::array([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
		'arr' => ['item'],
	]);

	Assert::same([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
		'arr' => ['item'],
	], $expectation->complete([]));

	Assert::same([
			'key1' => 'val1',
			'key2' => 'val2',
			'val3',
			'arr' => ['item'],
			1, 2, 3,
		],
		$expectation->complete([1, 2, 3])
	);

	Assert::same([
			'key1' => 'newval',
			'key2' => 'val2',
			'val3',
			'arr' => ['item', 'newitem'],
			'key3' => 'newval',
			'newval3',
		],
		$expectation->complete([
			'key1' => 'newval',
			'key3' => 'newval',
			'newval3', 'arr' => ['newitem'],
		])
	);
});


test(function () { // merging & other items validation
	$expectation = Expect::array([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
	])->otherItems('string');

	Assert::same([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
	], $expectation->complete([]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, 3]);
	}, InvalidConfigurationException::class, "The option '0' expects to be string, int 1 given.");

	Assert::same([
			'key1' => 'newval',
			'key2' => 'val2',
			'val3',
			'key3' => 'newval',
			'newval3',
		],
		$expectation->complete([
			'key1' => 'newval',
			'key3' => 'newval',
			'newval3',
		])
	);
});


test(function () { // otherItems() & scalar
	$expectation = Expect::array([
		'a' => 'defval',
	])->otherItems('string');

	Assert::same(['a' => 'defval'], $expectation->complete([]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, 3]);
	}, Nette\DI\InvalidConfigurationException::class, "The option '0' expects to be string, int 1 given.");

	Assert::same(['a' => 'val'], $expectation->complete(['a' => 'val']));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['a' => null]);
	}, Nette\DI\InvalidConfigurationException::class, "The option 'a' expects to be string, null given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => 123]);
	}, Nette\DI\InvalidConfigurationException::class, "The option 'b' expects to be string, int 123 given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => null]);
	}, InvalidConfigurationException::class, "The option 'b' expects to be string, null given.");

	Assert::same(['a' => 'defval', 'b' => 'val'], $expectation->complete(['b' => 'val']));
});


test(function () { // otherItems() & structure
	$expectation = Expect::array([
		'a' => 'defval',
	])->otherItems(Expect::structure(['k' => Expect::string()]));

	Assert::same(['a' => 'defval'], $expectation->complete([]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['a' => 'val']);
	}, Nette\DI\InvalidConfigurationException::class, "The option 'a' expects to be array, string 'val' given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, 3]);
	}, Nette\DI\InvalidConfigurationException::class, "The option '0' expects to be array, int 1 given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => 'val']);
	}, Nette\DI\InvalidConfigurationException::class, "The option 'b' expects to be array, string 'val' given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => ['a' => 'val']]);
	}, InvalidConfigurationException::class, "Unexpected option 'b › a', did you mean 'k'?");

	Assert::equal(
		['a' => 'defval', 'b' => (object) ['k' => 'val']],
		$expectation->complete(['b' => ['k' => 'val']])
	);
});


test(function () { // arrayOf() & scalar
	$expectation = Expect::arrayOf('string|int');

	Assert::same([], $expectation->complete([]));

	Assert::same([1, 2, 3], $expectation->complete([1, 2, 3]));

	Assert::same([10 => 20], $expectation->complete([10 => 20]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, false]);
	}, Nette\DI\InvalidConfigurationException::class, "The option '2' expects to be string or int, bool given.");

	Assert::same(['key' => 'val'], $expectation->complete(['key' => 'val']));
});


test(function () { // arrayOf() error
	Assert::exception(function () {
		Expect::arrayOf(['a' => Expect::string()]);
	}, TypeError::class);
});


test(function () { // type[]
	$expectation = Expect::type('int[]');

	Assert::same([], $expectation->complete(null));

	Assert::same([], $expectation->complete([]));

	Assert::same([1, 2, 3], $expectation->complete([1, 2, 3]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, false]);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be int[], array given.');

	Assert::same(['key' => 1], $expectation->complete(['key' => 1]));
});
