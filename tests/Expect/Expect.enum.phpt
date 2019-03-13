<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Nette\DI\InvalidConfigurationException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // with scalars
	$expectation = Expect::enum('one', true, Expect::int());

	Assert::same('one', $expectation->complete('one'));

	Assert::same(true, $expectation->complete(true));

	Assert::same(123, $expectation->complete(123));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(false);
	}, InvalidConfigurationException::class, "The option expects to be 'one'|true|int, false given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete('two');
	}, InvalidConfigurationException::class, "The option expects to be 'one'|true|int, 'two' given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(null);
	}, InvalidConfigurationException::class, "The option expects to be 'one'|true|int, null given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete([]);
	}, InvalidConfigurationException::class, "The option expects to be 'one'|true|int, array given.");
});


test(function () { // with complex structure
	$expectation = Expect::enum(Expect::listOf('string'), true, Expect::int());

	Assert::exception(function () use ($expectation) {
		$expectation->complete(false);
	}, InvalidConfigurationException::class, 'The option expects to be list|true|int, false given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete([123]);
	}, Nette\DI\InvalidConfigurationException::class, "The option '0' expects to be string, int 123 given.");

	Assert::same(['foo'], $expectation->complete(['foo']));
});


test(function () { // with asserts
	$expectation = Expect::enum(Expect::string()->assert('strlen'), true);

	Assert::exception(function () use ($expectation) {
		$expectation->complete('');
	}, InvalidConfigurationException::class, "The option expects to be string*|true, '' given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(123);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be string*|true, 123 given.');

	Assert::same('foo', $expectation->complete('foo'));
});


test(function () { // default value
	$expectation = Expect::structure([
		'key1' => Expect::enum(Expect::string(), Expect::int()),
		'key2' => Expect::enum(Expect::string('default'), true, Expect::int()),
		'key3' => Expect::enum(true, Expect::string('default'), Expect::int()),
	]);

	Assert::equal(
		(object) ['key1' => null, 'key2' => 'default', 'key3' => 'default'],
		$expectation->complete([])
	);
});
