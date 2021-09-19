<?php

/**
 * Test: Nette\DI\Helpers::escape()
 */

declare(strict_types=1);

use Nette\DI\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::same(123, Helpers::escape(123));
Assert::same('%%a%%', Helpers::escape('%a%'));
Assert::same('@@', Helpers::escape('@'));
Assert::same('x@', Helpers::escape('x@'));
Assert::same(
	['key1' => '%%', 'key2' => '@@', '%%a%%' => 123, '@' => 123],
	Helpers::escape(['key1' => '%', 'key2' => '@', '%a%' => 123, '@' => 123]),
);
