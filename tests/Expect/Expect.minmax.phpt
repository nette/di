<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Nette\DI\InvalidConfigurationException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // int & min
	$expectation = Expect::int()->min(10);

	Assert::same(10, $expectation->complete(10));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(9);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be int in range 10.., int 9 given.');
});


test(function () { // int & max
	$expectation = Expect::int()->max(20);

	Assert::same(20, $expectation->complete(20));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(21);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be int in range ..20, int 21 given.');
});


test(function () { // int & min & max
	$expectation = Expect::int()->min(10)->max(20);

	Assert::same(10, $expectation->complete(10));
	Assert::same(20, $expectation->complete(20));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(9);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be int in range 10..20, int 9 given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete(21);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be int in range 10..20, int 21 given.');
});


test(function () { // string
	$expectation = Expect::string()->min(1)->max(5);

	Assert::same('hello', $expectation->complete('hello'));
	Assert::same('x', $expectation->complete('x'));

	Assert::exception(function () use ($expectation) {
		$expectation->complete('');
	}, Nette\DI\InvalidConfigurationException::class, "The option expects to be string in range 1..5, string '' given.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete('foobar');
	}, Nette\DI\InvalidConfigurationException::class, "The option expects to be string in range 1..5, string 'foobar' given.");
});


test(function () { // array
	$expectation = Expect::array()->min(1)->max(3);

	Assert::same([1], $expectation->complete([1]));
	Assert::same([1, 2, 3], $expectation->complete([1, 2, 3]));

	Assert::exception(function () use ($expectation) {
		$expectation->complete([]);
	}, InvalidConfigurationException::class, 'The option expects to be array in range 1..3, array given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete([1, 2, 3, 4]);
	}, InvalidConfigurationException::class, 'The option expects to be array in range 1..3, array given.');
});
