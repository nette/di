<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


$services = check('
search:
	in: fixtures
	files: Ok*.php
	classes: Class*
	extends: stdClass
');

Assert::same([], $services);



$services = check('
search:
	in: fixtures
	files: Ok*.php
	classes: *Class
	extends: stdClass
');

Assert::same(['ExtendsStdClass'], array_keys($services));



$services = check('
search:
	in: fixtures
	classes: **\Class*
	extends: stdClass
	tags:
		n: 123
');

Assert::same(['Foo\Bar\ClassBar'], array_keys($services));
