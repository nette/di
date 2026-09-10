<?php declare(strict_types=1);

/**
 * Test: Nette\DI\ContainerBuilder: '__' is reserved for encoding dots
 */

use Nette\DI\ContainerBuilder;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


Assert::exception(
	fn() => (new ContainerBuilder)->addDefinition('a__b'),
	Nette\InvalidArgumentException::class,
	"Service name 'a__b' must not contain '__', which is reserved for encoding dots in method names.",
);


// a single underscore and a dot are fine
Assert::noError(function () {
	$builder = new ContainerBuilder;
	$builder->addDefinition('a_b');
	$builder->addDefinition('a.b');
});
