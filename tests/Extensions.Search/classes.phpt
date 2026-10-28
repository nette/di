<?php declare(strict_types=1);

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
	Foo\Bar\ClassBar::class,
	Foo\ClassBar::class,
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
	Foo\Bar\ClassBar::class,
	Foo\ClassBar::class,
	'InterfaceOk1',
	'InterfaceOk2',
], array_keys($services));



$services = check('
search:
	in: fixtures
	classes:
		- *\*Bar
');

Assert::same([Foo\ClassBar::class], array_keys($services));



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
	Foo\Bar\ClassBar::class,
	Foo\ClassBar::class,
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



// a class rejected by name is not loaded
$dir = getTempDir() . '/unloadable';
@mkdir($dir); // @ - directory may exist
file_put_contents("$dir/Unloadable.php", '<?php class UnloadableService extends MissingParent {}');

Assert::same([], check("
search:
	in: '$dir'
	exclude:
		classes: Unloadable*
"));

Assert::exception(
	fn() => check("
	search:
		in: '$dir'
	"),
	Nette\InvalidStateException::class,
	'Class UnloadableService was found, but it cannot be loaded by autoloading.',
);
