<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () {
	$expectation = Expect::int()->castTo('string');

	Assert::same('10', $expectation->complete(10));
});


test(function () {
	$expectation = Expect::string()->castTo('array');

	Assert::same(['foo'], $expectation->complete('foo'));
});


test(function () {
	$expectation = Expect::array()->castTo('stdClass');

	Assert::equal((object) ['a' => 1, 'b' => 2], $expectation->complete(['a' => 1, 'b' => 2]));
});
