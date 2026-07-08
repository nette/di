<?php declare(strict_types=1);

/**
 * Test: merging configs (K10) that contain expressions - creators, first-class callables
 * and setups survive merge/override/replace correctly.
 */

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\PartialCall;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function merge(string $override, string $base): array
{
	$loader = new DI\Config\Loader;
	$b = $loader->load(Tester\FileMock::create($base, 'neon'));
	$o = $loader->load(Tester\FileMock::create($override, 'neon'));
	return DI\Config\Helpers::merge($o, $b);
}


test('creator from base is kept, setups are appended', function () {
	$merged = merge(
		"services:\n\ta:\n\t\tsetup:\n\t\t\t- second()\n",
		"services:\n\ta:\n\t\tcreate: BaseClass\n\t\tsetup:\n\t\t\t- first()\n",
	);
	Assert::same('BaseClass', $merged['services']['a']['create']);
	Assert::count(2, $merged['services']['a']['setup']);
	Assert::type(Statement::class, $merged['services']['a']['setup'][0]);
});


test('override replaces the creator with a first-class callable', function () {
	$merged = merge(
		"services:\n\ta:\n\t\tcreate: ::trim(...)\n",
		"services:\n\ta:\n\t\tcreate: BaseClass\n",
	);
	Assert::type(PartialCall::class, $merged['services']['a']['create']);
	Assert::same('trim', $merged['services']['a']['create']->name);
});


test('replace operator (!) discards the base setup list', function () {
	$merged = merge(
		"services:\n\ta:\n\t\tsetup!:\n\t\t\t- only()\n",
		"services:\n\ta:\n\t\tcreate: BaseClass\n\t\tsetup:\n\t\t\t- first()\n\t\t\t- second()\n",
	);
	Assert::count(1, $merged['services']['a']['setup']);
	Assert::same('BaseClass', $merged['services']['a']['create']);
});
