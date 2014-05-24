<?php

/**
 * Test: Nette\DI\ContainerBuilder and inject methods.
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Ipsum
{
}

class Lorem
{
	public $injects;

	function injectIpsum(Ipsum $ipsum)
	{
		$this->injects[] = __METHOD__;
	}

	function inject($val)
	{
		$this->injects[] = __METHOD__ . ' ' . $val;
	}

	function injectOptional(DateTime $obj = NULL)
	{
		$this->injects[] = __METHOD__;
	}

}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('lorem')
	->setClass('Lorem')
	->addSetup('inject', array(123));

$builder->addDefinition('ipsum')
	->setClass('Ipsum');


$container = createContainer($builder);

Assert::same( array('Lorem::injectOptional', 'Lorem::inject 123', 'Lorem::injectIpsum'), $container->getService('lorem')->injects );
