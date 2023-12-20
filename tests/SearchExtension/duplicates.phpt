<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/Ok1.php';
require __DIR__ . '/fixtures/Ok2.php';


$services = check('
services:
	- ClassOk1
	- ClassOk2

search:
	in: fixtures
');

Assert::same([
	'ClassOk1',
	'ClassOk2',
	'ClassOk3',
	'CountableClass',
	'ExtendsStdClass',
	'Foo\\Bar\\ClassBar',
	'Foo\\ClassBar',
	'InterfaceOk1',
	'InterfaceOk2',
], array_keys($services));
