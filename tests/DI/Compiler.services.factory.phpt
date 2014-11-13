<?php

/**
 * Test: Nette\DI\Compiler: services factories.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	/** @return Lorem */
	static function createLorem($arg)
	{
		return new Lorem(__METHOD__ . ' ' . $arg);
	}

	static function getClass()
	{
		return 'Factory::createLorem';
	}
}


class Lorem
{
	function __construct($arg = NULL)
	{
		$this->arg = $arg;
	}

	/** @return Lorem */
	function foo()
	{
		$this->foo = func_get_args();
		return $this;
	}

}

class Ipsum
{
	function __construct($arg)
	{
		$this->arg = $arg;
	}
}


$container = createContainer(new DI\Compiler, 'files/compiler.services.factory.neon');


Assert::type( 'Ipsum', $container->getService('one') );
Assert::same( 1, $container->getService('one')->arg );

Assert::type( 'Ipsum', $container->getService('two') );
Assert::same( 1, $container->getService('two')->arg );

Assert::type( 'Lorem', $container->getService('three') );
Assert::same( 'Factory::createLorem 1', $container->getService('three')->arg );

Assert::type( 'Lorem', $container->getService('four') );
Assert::same( 'Factory::createLorem 1', $container->getService('four')->arg );

Assert::type( 'Lorem', $container->getService('five') );
Assert::same( 'Factory::createLorem 1', $container->getService('five')->arg );

Assert::type( 'Lorem', $container->getService('six') );
Assert::same( 'Factory::createLorem 1', $container->getService('six')->arg );

Assert::type( 'Lorem', $container->getService('seven') );

Assert::type( 'Lorem', $container->getService('eight') );

Assert::type( 'Ipsum', $container->getService('referencedService') );
Assert::same( $container->getService('one'), $container->getService('referencedService') );

Assert::type( 'Ipsum', $container->getService('referencedServiceWithSetup') );
Assert::notSame( $container->getService('one'), $container->getService('referencedServiceWithSetup') );

Assert::type( 'Ipsum', $container->getService('calledService') );
Assert::same( $container->getService('one'), $container->getService('calledService') ); // called without arguments is reference

Assert::type( 'Ipsum', $container->getService('calledServiceWithArgs') );
Assert::notSame( $container->getService('one'), $container->getService('calledServiceWithArgs') );

Assert::type( 'stdClass', $container->getByType('stdClass') );


Assert::type( 'Ipsum', $container->getService('serviceAsParam') );
Assert::type( 'Ipsum', $container->getService('serviceAsParam')->arg );
Assert::same( $container->getService('one'), $container->getService('serviceAsParam')->arg );

Assert::type( 'Ipsum', $container->getService('calledServiceAsParam') );
Assert::type( 'Ipsum', $container->getService('calledServiceAsParam')->arg );
Assert::notSame( $container->getService('one'), $container->getService('calledServiceAsParam')->arg );

Assert::type( 'Ipsum', $container->getService('calledServiceWithArgsAsParam') );
Assert::type( 'Ipsum', $container->getService('calledServiceWithArgsAsParam')->arg );
Assert::notSame( $container->getService('one'), $container->getService('calledServiceWithArgsAsParam')->arg );


Assert::type( 'Lorem', $container->getService('rich1') );
Assert::same( 1, $container->getService('rich1')->arg );
Assert::same( array(), $container->getService('rich1')->foo );

Assert::type( 'Lorem', $container->getService('rich2') );
Assert::type( 'Ipsum', $container->getService('rich2')->arg );
Assert::same( $container->getService('one'), $container->getService('rich2')->arg->arg );
Assert::same( array(1), $container->getService('rich2')->foo );

Assert::type( 'Lorem', $container->getService('rich3') );
Assert::same( 'Factory::createLorem 1', $container->getService('rich3')->arg );
Assert::same( array(), $container->getService('rich3')->foo );

Assert::type( 'Lorem', $container->getService('rich4') );
Assert::same( 'Factory::createLorem 1', $container->getService('rich4')->arg );
Assert::same( array(), $container->getService('rich4')->foo );
