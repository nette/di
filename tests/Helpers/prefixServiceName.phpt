<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Helpers::prefixServiceName() descends into expressions via transformValues().
 * This is the third traversal walker (besides expand() and filterArguments()) and had no coverage.
 */

use Nette\DI\Definitions\Statement;
use Nette\DI\Expressions\Reference;
use Nette\DI\Helpers;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('@extension. string prefix', function () {
	Assert::same('@my.svc', Helpers::prefixServiceName('@extension.svc', 'my'));
	Assert::same('@other', Helpers::prefixServiceName('@other', 'my')); // untouched
});


test('Reference with extension. prefix', function () {
	$prefixed = Helpers::prefixServiceName(new Reference('extension.svc'), 'my');
	Assert::type(Reference::class, $prefixed);
	Assert::same('my.svc', $prefixed->getValue());
});


test('prefix descends into Statement entity and arguments', function () {
	$statement = new Statement(['@extension.factory', 'create'], [new Reference('extension.dep')]);
	$prefixed = Helpers::prefixServiceName($statement, 'my');

	Assert::type(Statement::class, $prefixed);
	[$head] = $prefixed->getEntity();
	Assert::same('my.factory', $head->getValue());
	Assert::same('my.dep', $prefixed->arguments[0]->getValue());
});
