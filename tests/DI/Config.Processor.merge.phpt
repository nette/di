<?php

/**
 * Test: Nette\DI\Config\Processor::merge()
 */

declare(strict_types=1);

use Nette\DI\Config\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$processor = new Processor;
Assert::same(['key1' => 111, 'key2' => 2, 'key3' => 3], $processor->merge(['key1' => 1, 'key2' => 2], ['key1' => 111, 'key3' => 3]));

Assert::same(
	[
		'services' => [
			'foo' => ['factory' => 'class'],
		],
	],
	$processor->merge([], [
		'services' => [
			'foo' => 'class',
		],
	])
);
