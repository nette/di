<?php

/**
 * Test: ServiceDefinition
 */

declare(strict_types=1);

use Nette\DI\ServiceDefinition;
use Nette\DI\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test(function () {
	$def = new ServiceDefinition;
	$def->setType('Class');
	Assert::same('Class', $def->getType());
	Assert::null($def->getFactory());

	$def->setArguments([1, 2]);
	Assert::same('Class', $def->getType());
	Assert::equal(new Statement('Class', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	Assert::error(function () use ($def) {
		$def->setClass('Class', []);
	}, E_USER_DEPRECATED);
	Assert::same('Class', $def->getType());
	Assert::null($def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	Assert::error(function () use ($def) {
		$def->setClass('Class', [1, 2]);
	}, E_USER_DEPRECATED);
	Assert::same('Class', $def->getType());
	Assert::equal(new Statement('Class', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	$def->setFactory('Class');
	Assert::null($def->getType());
	Assert::equal(new Statement('Class', []), $def->getFactory());

	$def->setArguments([1, 2]);
	Assert::null($def->getType());
	Assert::equal(new Statement('Class', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	$def->setFactory('Class', [1, 2]);
	Assert::null($def->getType());
	Assert::equal(new Statement('Class', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	$def->setFactory(new Statement('Class', [1, 2]));
	Assert::null($def->getType());
	Assert::equal(new Statement('Class', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	$def->setFactory(new Statement('Class', [1, 2]), [99]); // 99 is ignored
	Assert::null($def->getType());
	Assert::equal(new Statement('Class', [1, 2]), $def->getFactory());
});

test(function () {
	$def = new ServiceDefinition;
	$def->addSetup('Class', [1, 2]);
	$def->addSetup(new Statement('Class', [1, 2]));
	$def->addSetup(new Statement('Class', [1, 2]), [99]); // 99 is ignored
	Assert::equal([
		new Statement('Class', [1, 2]),
		new Statement('Class', [1, 2]),
		new Statement('Class', [1, 2]),
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
	$def->setFactory(new Statement('Class', [1, 2]));
	$def->addSetup(new Statement('Class', [1, 2]));

	$dolly = clone $def;
	Assert::notSame($dolly->getFactory(), $def->getFactory());
	Assert::equal($dolly->getFactory(), $def->getFactory());
	Assert::notSame($dolly->getSetup(), $def->getSetup());
	Assert::equal($dolly->getSetup(), $def->getSetup());
});
