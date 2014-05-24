<?php

/**
 * Test: Nette\DI\ContainerBuilder and generated factories with inject methods.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Ipsum
{
}


class Lorem
{

	public $ipsum;

	public function injectIpsum(Ipsum $ipsum)
	{
		$this->ipsum = $ipsum;
	}

}


interface LoremFactory
{
	/** @return Lorem */
	function create();
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('lorem')
	->setImplement('LoremFactory');

$builder->addDefinition('ipsum')
	->setClass('Ipsum');


$container = createContainer($builder);

Assert::type( 'LoremFactory', $container->getService('lorem') );

$lorem = $container->getService('lorem')->create();

Assert::type( 'Lorem', $lorem );
Assert::type( 'Ipsum', $lorem->ipsum );
