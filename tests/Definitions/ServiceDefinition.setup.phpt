<?php declare(strict_types=1);

/**
 * Test: ServiceDefinition fluent DSL verbs setup(), clearSetup(), lazy(), tag(), autowired().
 */

use Nette\DI;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Expressions\Call;
use Nette\DI\Expressions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('setup() with a method name calls it on self', function () {
	$def = new ServiceDefinition;
	$def->setName('a')->setup('setLogger', ['x', 'y']);

	$setup = $def->getSetup();
	Assert::count(1, $setup);
	Assert::equal([new Reference(Reference::Self), 'setLogger'], $setup[0]->getEntity());
	Assert::same(['x', 'y'], $setup[0]->arguments);
});


test('setup() with $prop assigns a property', function () {
	$def = (new ServiceDefinition)->setName('a');
	$def->setup('$value', [123]);
	Assert::equal([new Reference(Reference::Self), '$value'], $def->getSetup()[0]->getEntity());
	Assert::same([123], $def->getSetup()[0]->arguments);
});


test('setup() accepts an expression step carrying its own arguments', function () {
	$def = (new ServiceDefinition)->setName('a');
	$step = di\service('bar')->method('register', [di\self()]);
	$def->setup($step);

	$stored = $def->getSetup()[0];
	Assert::type(Call::class, $stored);
	Assert::same('register', $stored->name);
});


test('setup() accepts the [Class, method] static form (concise, like the old API)', function () {
	$def = (new ServiceDefinition)->setName('a');
	$def->setup([stdClass::class, 'init'], [di\self()]);

	// a static call step, NOT prefixed with @self (array target is left as-is)
	Assert::same([stdClass::class, 'init'], $def->getSetup()[0]->getEntity());
	Assert::equal([new Reference(Reference::Self)], $def->getSetup()[0]->arguments);
});


test('setup() preserves named arguments', function () {
	$def = (new ServiceDefinition)->setName('a');
	$def->setup('configure', di\args(timeout: 30, retries: 3));
	Assert::same(['timeout' => 30, 'retries' => 3], $def->getSetup()[0]->arguments);
});


test('clearSetup() removes all steps', function () {
	$def = (new ServiceDefinition)->setName('a');
	$def->setup('a')->setup('b');
	Assert::count(2, $def->getSetup());
	$def->clearSetup();
	Assert::same([], $def->getSetup());
});


test('lazy(), tag(), removeTag(), autowired() aliases', function () {
	$def = (new ServiceDefinition)->setName('a');

	$def->lazy();
	Assert::true($def->isLazy());
	$def->lazy(false);
	Assert::false($def->isLazy());

	$def->tag('db', 'main');
	Assert::same('main', $def->getTag('db'));
	$def->removeTag('db');
	Assert::null($def->getTag('db'));

	$def->autowired(false);
	Assert::false($def->getAutowired());
});


test('setup() keeps strings literal (no @ decoding at the DSL layer)', function () {
	$def = (new ServiceDefinition)->setName('a');
	$def->setup('log', ['@notAReference', '%notAParam%']);
	Assert::same(['@notAReference', '%notAParam%'], $def->getSetup()[0]->arguments);
});
