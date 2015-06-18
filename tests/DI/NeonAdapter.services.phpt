<?php

/**
 * Test: Nette\DI\Config\Adapters\NeonAdapter
 */

use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Statement;
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
			new Nette\DI\Statement('Class2', ['arg2', 'arg3']),
		]),
	],
	$data
);
