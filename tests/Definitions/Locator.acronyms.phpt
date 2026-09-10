<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler: locator method names containing acronyms.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Lorem
{
}

interface Acronym
{
	function getPDO(): Lorem;

	function createURLFactory(): Lorem;
}

interface Digit
{
	function getHTTP2Client(): Lorem;
}


function compile(string $keys): DI\Container
{
	return createContainer(new DI\Compiler, "
	services:
		lorem: Lorem
		one: Acronym($keys)
	");
}


test('lowercased acronym', function () {
	$container = compile('pdo: Lorem, urlFactory: Lorem');
	Assert::type(Lorem::class, $container->getService('one')->getPDO());
	Assert::type(Lorem::class, $container->getService('one')->createURLFactory());
});


test('lcfirst form kept for compatibility', function () {
	$container = compile('pDO: Lorem, uRLFactory: Lorem');
	Assert::type(Lorem::class, $container->getService('one')->getPDO());
	Assert::type(Lorem::class, $container->getService('one')->createURLFactory());
});


test('acronym ends before a digit', function () {
	foreach (['http2Client', 'hTTP2Client'] as $key) {
		$container = createContainer(new DI\Compiler, "
		services:
			lorem: Lorem
			one: Digit($key: Lorem)
		");
		Assert::type(Lorem::class, $container->getService('one')->getHTTP2Client());
	}
});


test('no other spelling is accepted', function () {
	foreach (['PDO', 'Pdo', 'pDo'] as $key) {
		Assert::exception(
			fn() => compile("$key: Lorem, urlFactory: Lorem"),
			DI\ServiceCreationException::class,
			"Service 'one' (type of Acronym): Reference '$key' does not match any get*() or create*() method.",
		);
	}
});


test('reference matching no method', function () {
	Assert::exception(
		fn() => compile('pdo: Lorem, urlFactory: Lorem, typo: Lorem'),
		DI\ServiceCreationException::class,
		"Service 'one' (type of Acronym): Reference 'typo' does not match any get*() or create*() method.",
	);
});


test('method with no reference', function () {
	Assert::exception(
		fn() => compile('pdo: Lorem'),
		DI\ServiceCreationException::class,
		"Service 'one' (type of Acronym): Missing reference for method Acronym::createURLFactory().",
	);
});


test('both forms at once', function () {
	Assert::exception(
		fn() => compile('pdo: Lorem, pDO: Lorem, urlFactory: Lorem'),
		DI\ServiceCreationException::class,
		"Service 'one' (type of Acronym): References 'pdo' and 'pDO' both match method Acronym::getPDO().",
	);
});
