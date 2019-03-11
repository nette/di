<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


$services = check('
search:
	in: fixtures
	files:
');

Assert::same([
	'ClassOk1',
	'ClassOk2',
	'ClassOk3',
	'CountableClass',
	'ExtendsStdClass',
	'Foo\\Bar\\ClassBar',
	'Foo\\ClassBar',
], array_keys($services));



$services = check('
search:
	in: fixtures
	files: Ok*.php
');

Assert::same([
	'ClassOk1',
	'ClassOk2',
	'ClassOk3',
	'CountableClass',
	'ExtendsStdClass',
], array_keys($services));



$services = check('
search:
	in: fixtures
	files:
		- Ok*.php
		- Foo*.*
');

Assert::same([
	'ClassOk1',
	'ClassOk2',
	'ClassOk3',
	'CountableClass',
	'ExtendsStdClass',
	'Foo\\Bar\\ClassBar',
	'Foo\\ClassBar',
], array_keys($services));
