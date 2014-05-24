<?php

/**
 * Test: Nette\DI\Compiler: generated services accessors.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{
}

interface ILoremAccessor
{
	/** @return Lorem */
	function get();
}


$container = createContainer(new DI\Compiler, 'files/compiler.generatedAccessor.neon');


Assert::type( 'Lorem', $container->getService('lorem') );
Assert::notSame( $container->getService('lorem'), $container->getService('lorem2') );

Assert::type( 'ILoremAccessor', $container->getService('one') );
Assert::same( $container->getService('one')->get(), $container->getService('lorem') );

Assert::type( 'ILoremAccessor', $container->getService('two') );
Assert::same( $container->getService('two')->get(), $container->getService('lorem') );

Assert::type( 'ILoremAccessor', $container->getService('three') );
Assert::same( $container->getService('three')->get(), $container->getService('lorem2') );

Assert::type( 'ILoremAccessor', $container->getService('four') );
Assert::same( $container->getService('four')->get(), $container->getService('lorem') );
