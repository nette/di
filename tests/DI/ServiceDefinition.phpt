<?php

/**
 * Test: ServiceDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::exception(function () {
	$def = new ServiceDefinition;
	$def->setType('Foo');
}, Nette\InvalidArgumentException::class, "Service '': Class or interface 'Foo' not found.");

Assert::exception(function () {
	$def = new ServiceDefinition;
	$def->setImplement('Foo');
}, Nette\InvalidArgumentException::class, "Service '': Interface 'Foo' not found.");

Assert::exception(function () {
	$def = new ServiceDefinition;
	$def->setImplement('stdClass');
}, Nette\InvalidArgumentException::class, "Service '': Interface 'stdClass' not found.");

test(function () {
	$def = new ServiceDefinition;
	$def->setType('stdClass');
	Assert::same('stdClass', $def->getType());
	Assert::null($def->getFactory());

	$def->setArguments([1, 2]);
	Assert::same('stdClass', $def->getType());
	Assert::equal(new Statement('stdClass', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	Assert::error(function () use ($def) {
		$def->setClass('stdClass', []);
	}, E_USER_DEPRECATED);
	Assert::same('stdClass', $def->getType());
	Assert::null($def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	Assert::error(function () use ($def) {
		$def->setClass('stdClass', [1, 2]);
	}, E_USER_DEPRECATED);
	Assert::same('stdClass', $def->getType());
	Assert::equal(new Statement('stdClass', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	$def->setFactory('stdClass');
	Assert::null($def->getType());
	Assert::equal(new Statement('stdClass', []), $def->getFactory());

	$def->setArguments([1, 2]);
	Assert::null($def->getType());
	Assert::equal(new Statement('stdClass', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	$def->setFactory('stdClass', [1, 2]);
	Assert::null($def->getType());
	Assert::equal(new Statement('stdClass', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	$def->setFactory(new Statement('stdClass', [1, 2]));
	Assert::null($def->getType());
	Assert::equal(new Statement('stdClass', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	$def->setFactory(new Statement('stdClass', [1, 2]), [99]); // 99 is ignored
	Assert::null($def->getType());
	Assert::equal(new Statement('stdClass', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	$def->addSetup('stdClass', [1, 2]);
	$def->addSetup(new Statement('stdClass', [1, 2]));
	$def->addSetup(new Statement('stdClass', [1, 2]), [99]); // 99 is ignored
	Assert::equal([
		new Statement('stdClass', [1, 2]),
		new Statement('stdClass', [1, 2]),
		new Statement('stdClass', [1, 2]),
	], $def->getSetup());
});

test(function () {
	$def = new ServiceDefinition;
	$def->addTag('tag1');
	$def->addTag('tag2', [1, 2]);
	Assert::equal([
		'tag1' => true,
		'tag2' => [1, 2],
	], $def->getTags());

	Assert::equal(true, $def->getTag('tag1'));
	Assert::equal([1, 2], $def->getTag('tag2'));
	Assert::equal(null, $def->getTag('tag3'));
});

test(function () { // deep clone
	$def = new ServiceDefinition;
	$def->setFactory(new Statement('stdClass', [1, 2]));
	$def->addSetup(new Statement('stdClass', [1, 2]));

	$dolly = clone $def;
	Assert::notSame($dolly->getFactory(), $def->getFactory());
	Assert::equal($dolly->getFactory(), $def->getFactory());
	Assert::notSame($dolly->getSetup(), $def->getSetup());
	Assert::equal($dolly->getSetup(), $def->getSetup());
});
