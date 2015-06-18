<?php

/**
 * Test: Nette\DI\Compiler: service decorators.
 */

use Nette\DI;
use Nette\DI\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Iface
{
	const NAME = __CLASS__;
}


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
			- setup(Iface::NAME)
			- setup
		tags: [Iface::NAME, tag: 1]

	spec1:
		setup:
		tags:

	spec2:

services:
	one:
		class: Service
		tags: [a, tag: 2]
		setup:
			- setup(Service)
');


$builder = $compiler->getContainerBuilder();

Assert::same(
	['a' => TRUE, 'tag' => 2, 'inject' => TRUE, 'Iface' => TRUE],
	$builder->getDefinition('one')->getTags()
);

Assert::true($builder->getDefinition('one')->getTag('inject'));

Assert::equal([
	new Statement(['@self', 'setup'], ['Service']),
	new Statement(['@self', 'setup'], ['Object']),
	new Statement(['@self', 'setup'], ['Iface']),
	new Statement(['@self', 'setup']),
], $builder->getDefinition('one')->getSetup());
