<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Nette\DI\InvalidConfigurationException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // without default value
	$expectation = Expect::list();

	Assert::same([], $expectation->complete([]));

	Assert::same(['a', 'b', 'c'], $expectation->complete(['a', 'b', 'c']));

	Assert::exception(function () use ($expectation) {
		Assert::same(['key' => 'val'], $expectation->complete(['key' => 'val']));
	}, InvalidConfigurationException::class, 'The option expects to be list, array given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete('one');
	}, Nette\DI\InvalidConfigurationException::class, "The option expects to be list, string 'one' given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete(true);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be list, bool given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete(123);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be list, int 123 given.');

	Assert::same([], $expectation->complete(null));
});


test(function () { // merging
	$expectation = Expect::list([1, 2, 3]);

	Assert::same([1, 2, 3], $expectation->complete([]));

	Assert::same([1, 2, 3, 'a', 'b', 'c'], $expectation->complete(['a', 'b', 'c']));

	Assert::same([1, 2, 3], $expectation->complete(null));
});


test(function () { // merging & other items validation
	$expectation = Expect::list([1, 2, 3])->otherItems('string');

	Assert::same([1, 2, 3], $expectation->complete([]));

	Assert::same([1, 2, 3, 'a', 'b', 'c'], $expectation->complete(['a', 'b', 'c']));

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, 3]);
	}, Nette\DI\InvalidConfigurationException::class, "The option '0' expects to be string, int 1 given.");

	Assert::same([1, 2, 3], $expectation->complete(null));
});


test(function () { // listOf() & scalar
	$expectation = Expect::listOf('string');

	Assert::same([], $expectation->complete([]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, 3]);
	}, Nette\DI\InvalidConfigurationException::class, "The option '0' expects to be string, int 1 given.");

	Assert::same(['val', 'val'], $expectation->complete(['val', 'val']));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(['key' => 'val']);
	}, InvalidConfigurationException::class, 'The option expects to be list, array given.');
});


test(function () { // listOf() & error
	Assert::exception(function () {
		Expect::listOf(['a' => Expect::string()]);
	}, TypeError::class);
});
