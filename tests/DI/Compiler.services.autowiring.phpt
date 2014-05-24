<?php

/**
 * Test: Nette\DI\Compiler and autowiring.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	/** @return Model  auto-wiring using annotation */
	static function createModel()
	{
		return new Model;
	}
}


class Model
{
	/** autowiring using parameters */
	function test(Lorem $arg)
	{
		Notes::add(__METHOD__);
	}
}


class Lorem
{
	/** autowiring using parameters */
	static function test(Ipsum $arg)
	{
		Notes::add(__METHOD__);
	}
}

class Ipsum
{}


$container = createContainer(new DI\Compiler, 'files/compiler.services.autowiring.neon');


Assert::type( 'Model', $container->getService('model') );

Assert::same(array(
	'Model::test',
	'Model::test',
	'Model::test',
	'Lorem::test',
	'Lorem::test',
), Notes::fetch());
