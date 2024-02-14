<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


$services = check('
services:
	- ClassOk1
	-
		create: ClassOk2
		autowired: false

search:
	in: fixtures
	files:
		- Ok1.php
		- Ok2.php
	tags:
		- search
');

Assert::same([
	'ClassOk1' => [],
	'ClassOk2' => ['search' => true],
], $services);
