<?php

/**
 * Test: Nette\DI\Compiler and autowiring.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	/** @return Model  auto-wiring using annotation */
	public static function createModel()
	{
		return new Model;
	}
}


class Model
{
	/** autowiring using parameters */
	public function test(Lorem $arg)
	{
		Notes::add(__METHOD__);
	}
}


class Lorem
{
	/** autowiring using parameters */
	public static function test(Ipsum $arg)
	{
		Notes::add(__METHOD__);
	}
}

class Ipsum
{
}


$container = createContainer(new DI\Compiler, '
services:
	model:
		factory: Factory()::createModel
		setup:
			# local methods
			- test(...)
			- @model::test()
			- @self::test()

			# static class method
			- Lorem::test

			# other service method
			- @lorem::test

	lorem:
		factory: Lorem

	alias: @lorem

	ipsum:
		factory: Ipsum
');


Assert::type(Model::class, $container->getService('model'));

Assert::same([
	'Model::test',
	'Model::test',
	'Model::test',
	'Lorem::test',
	'Lorem::test',
], Notes::fetch());
