<?php

/**
 * Test: LocatorDefinition API
 */

declare(strict_types=1);

use Nette\DI\Definitions\LocatorDefinition;
use Nette\DI\Definitions\Reference;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Bad1
{
}

interface Bad2
{
	public function create(): stdClass;
}

interface Bad3
{
	public function get(): stdClass;
}

interface Bad4
{
	public function get($name): stdClass;

	public function foo(): stdClass;
}

interface Bad5
{
	public static function get($name): stdClass;
}

interface Bad6
{
	public function get($arg, $arg2): stdClass;
}

interface Good1
{
	public function get($name): stdClass;
}

interface Good2
{
	public function create($name): stdClass;
}

interface Good3
{
	public function createA(): stdClass;

	public function getB(): stdClass;
}


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setType('Foo');
}, Nette\MemberAccessException::class);


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement('Foo');
}, Nette\InvalidArgumentException::class, "Service '': Interface 'Foo' not found.");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement(stdClass::class);
}, Nette\InvalidArgumentException::class, "Service '': Interface 'stdClass' not found.");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement(Bad1::class);
}, Nette\InvalidArgumentException::class, "Service '': Interface Bad1 must have at least one method.");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement(Bad2::class);
}, Nette\InvalidArgumentException::class, "Service '': Method Bad2::create() does not meet the requirements: is create(\$name), get(\$name), create*() or get*() and is non-static.");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement(Bad3::class);
}, Nette\InvalidArgumentException::class, "Service '': Method Bad3::get() does not meet the requirements: is create(\$name), get(\$name), create*() or get*() and is non-static.");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement(Bad4::class);
}, Nette\InvalidArgumentException::class, "Service '': Method Bad4::foo() does not meet the requirements: is create(\$name), get(\$name), create*() or get*() and is non-static.");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement(Bad5::class);
}, Nette\InvalidArgumentException::class, "Service '': Method Bad5::get() does not meet the requirements: is create(\$name), get(\$name), create*() or get*() and is non-static.");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement(Bad6::class);
}, Nette\InvalidArgumentException::class, "Service '': Method Bad6::get() does not meet the requirements: is create(\$name), get(\$name), create*() or get*() and is non-static.");


Assert::noError(function () {
	$def = new LocatorDefinition;
	$def->setImplement(Good1::class);
	Assert::same(Good1::class, $def->getImplement());
	Assert::same(Good1::class, $def->getType());
});


Assert::noError(function () {
	$def = new LocatorDefinition;
	$def->setImplement(Good2::class);
	Assert::same(Good2::class, $def->getImplement());
	Assert::same(Good2::class, $def->getType());
});


Assert::noError(function () {
	$def = new LocatorDefinition;
	$def->setImplement(Good3::class);
	Assert::same(Good3::class, $def->getImplement());
	Assert::same(Good3::class, $def->getType());
});


test('', function () {
	$def = new LocatorDefinition;
	$def->setImplement(Good1::class);

	$def->setReferences(['a' => 'stdClass', 'b' => '@one']);
	Assert::equal(['a' => new Reference('\stdClass'), 'b' => new Reference('one')], $def->getReferences());

	$def->setTagged('tagName');
	Assert::same('tagName', $def->getTagged());
});
