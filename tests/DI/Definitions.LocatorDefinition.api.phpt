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
	public function create();
}

interface Bad3
{
	public function get();
}

interface Bad4
{
	public function get($name);

	public function foo();
}

interface Bad5
{
	public static function get($name);
}

interface Bad6
{
	public function get($arg, $arg2);
}

interface Good1
{
	public function get($name);
}

interface Good2
{
	public function create($name);
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
	$def->setImplement('stdClass');
}, Nette\InvalidArgumentException::class, "Service '': Interface 'stdClass' not found.");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement('Bad1');
}, Nette\InvalidArgumentException::class, "Service '': Interface Bad1 must have just one non-static method create() or get().");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement('Bad2');
}, Nette\InvalidArgumentException::class, "Service '': Method Bad2::create() must have one parameter.");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement('Bad3');
}, Nette\InvalidArgumentException::class, "Service '': Method Bad3::get() must have one parameter.");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement('Bad4');
}, Nette\InvalidArgumentException::class, "Service '': Interface Bad4 must have just one non-static method create() or get().");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement('Bad5');
}, Nette\InvalidArgumentException::class, "Service '': Interface Bad5 must have just one non-static method create() or get().");


Assert::exception(function () {
	$def = new LocatorDefinition;
	$def->setImplement('Bad6');
}, Nette\InvalidArgumentException::class, "Service '': Method Bad6::get() must have one parameter.");


Assert::noError(function () {
	$def = new LocatorDefinition;
	$def->setImplement('Good1');
	Assert::same('Good1', $def->getImplement());
	Assert::same('Good1', $def->getType());
});


Assert::noError(function () {
	$def = new LocatorDefinition;
	$def->setImplement('Good2');
	Assert::same('Good2', $def->getImplement());
	Assert::same('Good2', $def->getType());
});


test(function () {
	$def = new LocatorDefinition;
	$def->setImplement('Good1');

	$def->setReferences(['a' => 'stdClass', 'b' => '@one']);
	Assert::equal(['a' => new Reference('\stdClass'), 'b' => new Reference('one')], $def->getReferences());

	$def->setTagged('tagName');
	Assert::same('tagName', $def->getTagged());
});
