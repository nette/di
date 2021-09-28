<?php

/**
 * Test: FactoryDefinition API
 */

declare(strict_types=1);

use Nette\DI\Definitions\FactoryDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Bad1
{
}

interface Bad2
{
	public function get();
}

interface Bad3
{
	public function create();

	public function foo();
}

interface Bad4
{
	public static function create();
}

interface Good1
{
	public function create(): stdClass;
}


Assert::exception(function () {
	$def = new FactoryDefinition;
	$def->setImplement('Foo');
}, Nette\InvalidArgumentException::class, "Service '': Interface 'Foo' not found.");


Assert::exception(function () {
	$def = new FactoryDefinition;
	$def->setImplement(stdClass::class);
}, Nette\InvalidArgumentException::class, "Service '': Interface 'stdClass' not found.");


Assert::exception(function () {
	$def = new FactoryDefinition;
	$def->setImplement(Bad1::class);
}, Nette\InvalidArgumentException::class, "Service '': Interface Bad1 must have just one non-static method create().");


Assert::exception(function () {
	$def = new FactoryDefinition;
	$def->setImplement(Bad2::class);
}, Nette\InvalidArgumentException::class, "Service '': Interface Bad2 must have just one non-static method create().");


Assert::exception(function () {
	$def = new FactoryDefinition;
	$def->setImplement(Bad3::class);
}, Nette\InvalidArgumentException::class, "Service '': Interface Bad3 must have just one non-static method create().");


Assert::exception(function () {
	$def = new FactoryDefinition;
	$def->setImplement(Bad4::class);
}, Nette\InvalidArgumentException::class, "Service '': Interface Bad4 must have just one non-static method create().");


Assert::noError(function () {
	$def = new FactoryDefinition;
	@$def->setImplement(Good1::class); // missing type triggers warning
	Assert::same(Good1::class, $def->getImplement());
	Assert::same(Good1::class, $def->getType());
});


test('', function () {
	$def = new FactoryDefinition;
	$def->setImplement(Good1::class);

	Assert::null($def->getResultType());

	$resDefinition = $def->getResultDefinition();
	Assert::type(Nette\DI\Definitions\ServiceDefinition::class, $resDefinition);

	$resDefinition->setType(stdClass::class);
	Assert::same(stdClass::class, $def->getResultType());
});
