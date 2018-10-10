<?php

/**
 * Test: Nette\DI\Config\Adapters\NeonAdapter
 */

declare(strict_types=1);

use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$adapter = new NeonAdapter;
$data = $adapter->load(Tester\FileMock::create('
- Class(arg1, Class2(arg2, arg3))
', 'neon'));

Assert::equal(
	[
		new Statement('Class', [
			'arg1',
			new Statement('Class2', ['arg2', 'arg3']),
		]),
	],
	$data
);
