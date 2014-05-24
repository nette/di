<?php

/**
 * Test: Nette\DI\Compiler: services by Class.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{
	function __construct(Ipsum $arg)
	{
	}
}

class Ipsum
{
	static function foo()
	{
	}
}


$container = createContainer(new DI\Compiler, 'files/compiler.services.byClass.neon');


Assert::type( 'Lorem', $container->getService('one') );
Assert::type( 'Ipsum', $container->getService('two') );
Assert::type( 'Lorem', $container->getService('three') );
Assert::same( $container->getService('one'), $container->getService('three') );
Assert::type( 'Lorem', $container->getService('four') );
Assert::same( $container->getService('one'), $container->getService('four') );
