<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


$services = check('
search:
	in: fixtures
	classes:
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



$services = check('
search:
	in: fixtures
	classes: Foo*
');

Assert::same([], $services);



$services = check('
search:
	in: fixtures
	classes:
		- FOO\**
		- *OK*
');

Assert::same([
	'ClassOk1',
	'ClassOk2',
	'ClassOk3',
	'Foo\\Bar\\ClassBar',
	'Foo\\ClassBar',
	'InterfaceOk1',
	'InterfaceOk2',
], array_keys($services));



$services = check('
search:
	in: fixtures
	classes:
		- *\*Bar
');

Assert::same(['Foo\\ClassBar'], array_keys($services));



$services = check('
search:
	in: fixtures
	classes:
		- **\*Class*
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
	exclude:
		classes: *Bar*
');

Assert::same([
	'ClassOk1',
	'ClassOk2',
	'ClassOk3',
	'CountableClass',
	'ExtendsStdClass',
	'InterfaceOk1',
	'InterfaceOk2',
], array_keys($services));
