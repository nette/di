<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler: properties, static properties and constants in arguments and chains.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Item
{
	public const Constant = 'constant';

	public static self $staticProperty;
	public self $property;


	public function __construct(
		public string $value = 'service',
	) {
		if ($value !== 'property') {
			$this->property = new self('property');
		}
		if ($value === 'service') {
			self::$staticProperty = new self('static');
		}
	}


	public function getObject(): self
	{
		return new self('object');
	}


	public function getValue(): string
	{
		return $this->value;
	}
}


class Holder
{
	public function __construct(
		public mixed $arg,
	) {
	}
}


function load(string $arg): mixed
{
	$container = createContainer(new DI\Compiler, "
services:
	item: Item
	holder: Holder($arg)
");
	$arg = $container->getService('holder')->arg;
	return $arg instanceof Item ? $arg->value : $arg;
}


test('member of a service', function () {
	Assert::same('property', load('@item::property'));
	Assert::same('static', load('@item::$staticProperty'));
	Assert::same('constant', load('@item::Constant'));
	Assert::same('object', load('@item::getObject()'));
});


test('member of a class', function () {
	Assert::same('static', load('Item::$staticProperty'));
	Assert::same('constant', load('Item::Constant'));
});


test('a link without parentheses reads a member', function () {
	Assert::same('property', load('@item::getObject()::property::value'));
	Assert::same('static', load('@item::getObject()::$staticProperty'));
	Assert::same('constant', load('@item::getObject()::Constant'));
});


test('members before a call', function () {
	Assert::same('property', load('@item::property::getValue()'));
	Assert::same('static', load('@item::$staticProperty::getValue()'));
	Assert::same('object', load('@item::property::getObject()::getValue()'));
});


test('members inside a chain', function () {
	Assert::same('property', load('@item::getObject()::property::getValue()'));
	Assert::same('static', load('@item::getObject()::$staticProperty::getValue()'));
});


test('assignment in setup', function () {
	$container = createContainer(new DI\Compiler, '
services:
	item: Item
	holder:
		create: Holder(1)
		setup:
			- @item::property = Item(assigned)
			- @item::$staticProperty = Item(static)
			- @item::$staticProperty(Item(static2))
');
	$container->getService('holder');
	Assert::same('assigned', $container->getService('item')->property->value);
	Assert::same('static2', Item::$staticProperty->value);
});


test('a method written without parentheses', function () {
	Assert::exception(
		fn() => load('@item::getObject()::getValue'),
		DI\ServiceCreationException::class,
		"%a%Class Item has no property '\$getValue', only a method; write 'getValue()' to call it.%a%",
	);
});


test('an undefined constant', function () {
	Assert::exception(
		fn() => load('Item::Undefined'),
		DI\InvalidConfigurationException::class,
		"Constant 'Item::Undefined' is not defined (used in %a%",
	);
});
