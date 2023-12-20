<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


$services = check('
search:
	in: fixtures
	files: Ok*.php
	tags:
		a: 1
		b: 2
');

Assert::same([
	'ClassOk1' => ['a' => 1, 'b' => 2],
	'ClassOk2' => ['a' => 1, 'b' => 2],
	'ClassOk3' => ['a' => 1, 'b' => 2],
	'CountableClass' => ['a' => 1, 'b' => 2],
	'ExtendsStdClass' => ['a' => 1, 'b' => 2],
	'InterfaceOk1' => ['a' => 1, 'b' => 2],
	'InterfaceOk2' => ['a' => 1, 'b' => 2],
], $services);



$services = check('
search:
	in: fixtures
	files: Ok*.php
	tags:
');

Assert::same([
	'ClassOk1' => [],
	'ClassOk2' => [],
	'ClassOk3' => [],
	'CountableClass' => [],
	'ExtendsStdClass' => [],
	'InterfaceOk1' => [],
	'InterfaceOk2' => [],
], $services);
