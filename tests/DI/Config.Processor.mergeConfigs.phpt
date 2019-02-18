<?php

/**
 * Test: Nette\DI\Config\Processor::mergeConfigs()
 */

declare(strict_types=1);

use Nette\DI\Config\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$processor = new Processor(new Nette\DI\ContainerBuilder);

Assert::same(
	[
		'foo' => ['factory' => 'class'],
	],
	$processor->mergeConfigs([
		'foo' => 'class',
	], [])
);
