<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Nette\DI\InvalidConfigurationException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // without items
	$expectation = Expect::structure([]);

	Assert::equal((object) [], $expectation->complete([]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, 3]);
	}, Nette\DI\InvalidConfigurationException::class, "Unexpected option '0', '1', '2'.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['key' => 'val']);
	}, Nette\DI\InvalidConfigurationException::class, "Unexpected option 'key'.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete('one');
	}, Nette\DI\InvalidConfigurationException::class, "The option expects to be array, string 'one' given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(true);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be array, bool given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete(123);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be array, int 123 given.');

	Assert::equal((object) [], $expectation->complete(null));
});


test(function () { // scalar items
	$expectation = Expect::structure([
		'a' => Expect::string(),
		'b' => Expect::int(),
		'c' => Expect::bool(),
		'd' => Expect::scalar(),
		'e' => Expect::type('string'),
		'f' => Expect::type('int'),
		'g' => Expect::string('abc'),
		'h' => Expect::string(123),
		'i' => Expect::type('string')->default(123),
		'j' => Expect::enum(1, 2),
	]);

	Assert::equal(
		(object) ['a' => null, 'b' => null, 'c' => null, 'd' => null, 'e' => null, 'f' => null, 'g' => 'abc', 'h' => 123, 'i' => 123, 'j' => null],
		$expectation->complete([])
	);
});


test(function () { // array items
	$expectation = Expect::structure([
		'a' => Expect::array(),
		'b' => Expect::array([]),
		'c' => Expect::arrayOf('string'),
		'd' => Expect::list(),
		'e' => Expect::listOf('string'),
		'f' => Expect::type('array'),
		'g' => Expect::type('list'),
		'h' => Expect::structure([]),
	]);

	Assert::equal(
		(object) ['a' => [], 'b' => [], 'c' => [], 'd' => [], 'e' => [], 'f' => [], 'g' => [], 'h' => (object) []],
		$expectation->complete([])
	);
});


test(function () { // default value must be readonly
	Assert::exception(function () {
		$expectation = Expect::structure([])->default([]);
	}, Nette\InvalidStateException::class);
});


test(function () { // with indexed item
	$expectation = Expect::structure([
		'key1' => 'val1',
		'key2' => 'val2',
		'val3',
		'arr' => ['item'],
	]);

	Assert::equal(
		(object) [
			'key1' => 'val1',
			'key2' => 'val2',
			'val3',
			'arr' => ['item'],
		],
		$expectation->complete([])
	);

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, 3]);
	}, InvalidConfigurationException::class, "Unexpected option '1', '2'.");

	Assert::equal(
		(object) [
			'key1' => 'newval',
			'key2' => 'val2',
			'newval3',
			'arr' => ['item'],
		],
		$expectation->complete(['key1' => 'newval', 'newval3'])
	);
});


test(function () { // item with default value
	$expectation = Expect::structure([
		'a' => 'defval',
		'b' => Expect::string(123),
	]);

	Assert::equal((object) ['a' => 'defval', 'b' => 123], $expectation->complete([]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, 3]);
	}, InvalidConfigurationException::class, "Unexpected option '0', did you mean 'a'?");

	Assert::equal((object) ['a' => 'val', 'b' => 123], $expectation->complete(['a' => 'val']));

	Assert::equal((object) ['a' => null, 'b' => 123], $expectation->complete(['a' => null]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => 123]);
	}, Nette\DI\InvalidConfigurationException::class, "The option 'b' expects to be string, int 123 given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => null]);
	}, InvalidConfigurationException::class, "The option 'b' expects to be string, null given.");

	Assert::equal((object) ['a' => 'defval', 'b' => 'val'], $expectation->complete(['b' => 'val']));
});


test(function () { // item without default value
	$expectation = Expect::structure([
		'a' => 'defval',
		'b' => Expect::string(),
	]);

	Assert::equal((object) ['a' => 'defval', 'b' => null], $expectation->complete([]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => 123]);
	}, Nette\DI\InvalidConfigurationException::class, "The option 'b' expects to be string, int 123 given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => null]);
	}, InvalidConfigurationException::class, "The option 'b' expects to be string, null given.");

	Assert::equal((object) ['a' => 'defval', 'b' => 'val'], $expectation->complete(['b' => 'val']));
});


test(function () { // required item
	$expectation = Expect::structure([
		'a' => 'defval',
		'b' => Expect::string()->required(),
		'c' => Expect::array()->required(),
	]);

	Assert::exception(function () use ($expectation) {
		$expectation->complete([]);
	}, InvalidConfigurationException::class, "The mandatory option 'b' is missing.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => 'val']);
	}, InvalidConfigurationException::class, "The mandatory option 'c' is missing.");

	Assert::equal(
		(object) ['a' => 'defval', 'b' => 'val', 'c' => [1, 2, 3]],
		$expectation->complete(['b' => 'val', 'c' => [1, 2, 3]])
	);
});


test(function () { // structure items
	$expectation = Expect::structure([
		'a' => Expect::structure([
			'x' => Expect::string('defval'),
		]),
		'b' => Expect::structure([
			'y' => Expect::string()->required(),
		]),
	]);

	Assert::exception(function () use ($expectation) {
		$expectation->complete([]);
	}, InvalidConfigurationException::class, "The mandatory option 'b › y' is missing.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, 3]);
	}, InvalidConfigurationException::class, "Unexpected option '0', did you mean 'a'?");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['a' => 'val']);
	}, Nette\DI\InvalidConfigurationException::class, "The option 'a' expects to be array, string 'val' given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['a' => null]);
	}, InvalidConfigurationException::class, "The mandatory option 'b › y' is missing.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => 123]);
	}, Nette\DI\InvalidConfigurationException::class, "The option 'b' expects to be array, int 123 given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => null]);
	}, InvalidConfigurationException::class, "The mandatory option 'b › y' is missing.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => 'val']);
	}, Nette\DI\InvalidConfigurationException::class, "The option 'b' expects to be array, string 'val' given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => ['x' => 'val']]);
	}, InvalidConfigurationException::class, "Unexpected option 'b › x', did you mean 'y'?");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => ['x1' => 'val', 'x2' => 'val']]);
	}, InvalidConfigurationException::class, "Unexpected option 'b › x1', 'b › x2'.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['b' => ['y' => 123]]);
	}, Nette\DI\InvalidConfigurationException::class, "The option 'b › y' expects to be string, int 123 given.");

	Assert::equal(
		(object) ['a' => (object) ['x' => 'defval'], 'b' => (object) ['y' => 'val']],
		$expectation->complete(['b' => ['y' => 'val']])
	);
});
