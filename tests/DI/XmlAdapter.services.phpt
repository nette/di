<?php

/**
 * Test: Nette\DI\Config\Adapters\XmlAdapter
 */

use Nette\DI\Config\Adapters\XmlAdapter;
use Nette\DI\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$adapter = new XmlAdapter;
$data = $adapter->load('files/xmlAdapter.services.xml');

Assert::equal(
	[
		new Statement('Class', [
			'arg1',
			new Nette\DI\Statement('Class2', ['arg2', 'arg3']),
		]),
		new Statement('Class', [
			'arg1',
			new Nette\DI\Statement('Class2', ['arg2', 'arg3']),
		]),
	],
	$data
);
