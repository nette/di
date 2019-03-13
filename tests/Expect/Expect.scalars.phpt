<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Nette\DI\InvalidConfigurationException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () {
	$expectation = Expect::scalar();

	Assert::same('hello', $expectation->complete('hello'));
	Assert::same(123, $expectation->complete(123));
	Assert::same(false, $expectation->complete(false));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(null);
	}, InvalidConfigurationException::class, 'The option expects to be scalar, null given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete([]);
	}, InvalidConfigurationException::class, 'The option expects to be scalar, array given.');
});


test(function () {
	$expectation = Expect::string();

	Assert::same('hello', $expectation->complete('hello'));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(123);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be string, int 123 given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete(null);
	}, InvalidConfigurationException::class, 'The option expects to be string, null given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete(false);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be string, bool given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete([]);
	}, InvalidConfigurationException::class, 'The option expects to be string, array given.');
});


test(function () {
	$expectation = Expect::type('string|bool');

	Assert::same('one', $expectation->complete('one'));

	Assert::same(true, $expectation->complete(true));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(123);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be string or bool, int 123 given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete(null);
	}, InvalidConfigurationException::class, 'The option expects to be string or bool, null given.');

	Assert::exception(function () use ($expectation) {
		$expectation->complete([]);
	}, InvalidConfigurationException::class, 'The option expects to be string or bool, array given.');
});


test(function () {
	$expectation = Expect::type('string')->nullable();

	Assert::same('one', $expectation->complete('one'));

	Assert::exception(function () use ($expectation) {
		$expectation->complete(123);
	}, Nette\DI\InvalidConfigurationException::class, 'The option expects to be string or null, int 123 given.');

	Assert::same(null, $expectation->complete(null));
});
