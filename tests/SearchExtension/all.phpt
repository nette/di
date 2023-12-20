<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


$services = check('
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



$services = check('
search:
	in: fixtures/subdir
');

Assert::same(['ClassOk3'], array_keys($services));
