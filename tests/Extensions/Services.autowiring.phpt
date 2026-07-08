<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler and autowiring.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	public static function createModel(): Model
	{
		return new Model;
	}
}


class Model
{
	public static array $calledMethods = [];


	/** autowiring using parameters */
	public function test(Lorem $arg)
	{
		self::$calledMethods[] = __METHOD__;
	}
}


class Lorem
{
	/** autowiring using parameters */
	public static function test(Ipsum $arg)
	{
		Model::$calledMethods[] = __METHOD__;
	}
}

class Ipsum
{
}


$container = createContainer(new DI\Compiler, '
services:
	model:
		create: Factory()::createModel
		setup:
			# local methods
			- test(_)
			- @model::test()
			- @self::test()

			# static class method
			- Lorem::test

			# other service method
			- @lorem::test

	lorem:
		create: Lorem

	alias: @lorem

	ipsum:
		create: Ipsum
');


Assert::type(Model::class, $container->getService('model'));

Assert::same([
	'Model::test',
	'Model::test',
	'Model::test',
	'Lorem::test',
	'Lorem::test',
], Model::$calledMethods);
