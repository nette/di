<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Nette\DI\InvalidConfigurationException;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () { // single assertion
	$expectation = Expect::string()->assert('is_file');

	Assert::exception(function () use ($expectation) {
		$expectation->complete('hello');
	}, InvalidConfigurationException::class, "Failed assertion is_file() for option with value 'hello'.");

	Assert::same(__FILE__, $expectation->complete(__FILE__));
});


test(function () { // multiple assertions
	$expectation = Expect::string()->assert('ctype_digit')->assert(function ($s) { return strlen($s) >= 3; });

	Assert::exception(function () use ($expectation) {
		$expectation->complete('');
	}, InvalidConfigurationException::class, "Failed assertion ctype_digit() for option with value ''.");

	Assert::exception(function () use ($expectation) {
		$expectation->complete('1');
	}, InvalidConfigurationException::class, "Failed assertion #1 for option with value '1'.");

	Assert::same('123', $expectation->complete('123'));
});
