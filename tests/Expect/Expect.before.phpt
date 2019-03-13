<?php

declare(strict_types=1);

use Nette\DI\Config\Expect;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () {
	$expectation = Expect::array()->normalize(function ($val) { return explode(',', $val); });

	Assert::same(['1', '2', '3'], $expectation->flatten(['1,2,3']));
});
