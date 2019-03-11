<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


$services = check('
search:
	in: fixtures
	extends:
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
	extends: stdClass
');

Assert::same(['ExtendsStdClass', 'Foo\\Bar\\ClassBar'], array_keys($services));



$services = check('
search:
	in: fixtures
	extends:
		- stdClass
		- Countable
');

Assert::same(['CountableClass', 'ExtendsStdClass', 'Foo\\Bar\\ClassBar'], array_keys($services));



$services = check('
search:
	in: fixtures
	implements:
		- stdClass
	extends:
		- Countable
');

Assert::same(['CountableClass', 'ExtendsStdClass', 'Foo\\Bar\\ClassBar'], array_keys($services));



$services = check('
search:
	in: fixtures
	extends: ClassOk1
');

Assert::same([], $services);



Assert::exception(function () {
	check('
	search:
		in: fixtures
		extends: unknown
	');
}, ReflectionException::class, 'Class unknown does not exist');



$services = check('
search:
	in: fixtures
	exclude:
		extends: stdClass
');

Assert::same(
	['ClassOk1', 'ClassOk2', 'ClassOk3', 'CountableClass', 'Foo\\ClassBar'],
	array_keys($services)
);
