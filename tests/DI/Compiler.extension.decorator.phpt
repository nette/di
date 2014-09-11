<?php

/**
 * Test: Nette\DI\Compiler: service decorators.
 */

use Nette\DI,
	Nette\DI\Statement,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Iface
{}


class Service extends Nette\Object implements Iface
{
	public $setup;

	function setup($a = NULL)
	{
		$this->setup[] = $a;
	}
}


$compiler = new DI\Compiler;
$compiler->addExtension('decorator', new Nette\DI\Extensions\DecoratorExtension);
$container = createContainer($compiler, '
decorator:
	Nette\Object:
		setup:
			- setup(Object)
		inject: yes

	Iface:
		setup:
			- setup(Iface)
			- setup
		tags: [b, tag: 1]

services:
	one:
		class: Service
		tags: [a, tag: 2]
		setup:
			- setup(Service)
');


$builder = $compiler->getContainerBuilder();

Assert::same(
	array('a' => TRUE, 'tag' => 2, 'inject' => TRUE, 'b' => TRUE),
	$builder->getDefinition('one')->tags
);

Assert::true( $builder->getDefinition('one')->getTag('inject') );

Assert::equal( array(
	new Statement(array('@self', 'setup'), array('Service')),
	new Statement(array('@self', 'setup'), array('Object')),
	new Statement(array('@self', 'setup'), array('Iface')),
	new Statement(array('@self', 'setup')),
), $builder->getDefinition('one')->getSetup() );
