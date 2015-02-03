<?php

/**
 * Test: Nette\DI\ContainerBuilder and rich syntax.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	/** @return Obj */
	function create()
	{
		return new Obj;
	}

	function mark(Obj $obj)
	{
		$obj->mark = TRUE;
	}
}

class Obj
{
	/** @return Obj */
	function foo($arg)
	{
		$this->args[] = $arg;
		return $this;
	}
}


$builder = new DI\ContainerBuilder;
$one = $builder->addDefinition('one')
	->setFactory(array(new DI\Statement('Factory'), 'create'))
	->addSetup(array(new DI\Statement('Factory'), 'mark'), array('@self'));

$two = $builder->addDefinition('two')
	->setFactory(array(new DI\Statement(array($one, 'foo'), array(1)), 'foo'), array(2));


$container = createContainer($builder);

Assert::same( 'Obj', $one->getClass() );
Assert::type( 'Obj', $container->getService('one') );
Assert::true( $container->getService('one')->mark );

Assert::same( 'Obj', $two->getClass() );
Assert::type( 'Obj', $container->getService('two') );
Assert::true( $container->getService('two')->mark );
Assert::same( array(1, 2), $container->getService('two')->args );
