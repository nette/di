<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


$services = check('
search:
	first:
		in: fixtures
		files: Ok*.php
		tags:
			- ok

	second:
		in: fixtures/subdir
		tags:
			- subdir

	third:
		in: fixtures
		files: Foo*
		tags:
			- foo
');

Assert::same([
	'ClassOk1' => ['ok' => true],
	'ClassOk2' => ['ok' => true],
	'ClassOk3' => ['ok' => true, 'subdir' => true],
	'CountableClass' => ['ok' => true],
	'ExtendsStdClass' => ['ok' => true],
	'Foo\\Bar\\ClassBar' => ['foo' => true],
	'Foo\\ClassBar' => ['foo' => true],
	'InterfaceOk1' => ['ok' => true],
	'InterfaceOk2' => ['ok' => true],
], $services);
