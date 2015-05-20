<?php

/**
 * Test: Nette\DI\Compiler: inject.
 */

use Nette\DI,
	Nette\DI\Statement,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class ParentClass
{
	/** @var stdClass @inject */
	public $a;

	/** @var stdClass @inject */
	protected $b;

	function injectA() {}
	function injectB() {}
}

class Service extends ParentClass
{
	/** @var stdClass @inject */
	public $c;

	/** @var stdClass @inject */
	protected $d;

	function injectC() {}
	function injectD() {}
}



class LastExtension extends DI\CompilerExtension
{
	private $param;

	function beforeCompile()
	{
		// note that services should be added in loadConfiguration()
		$this->getContainerBuilder()->addDefinition('one')
			->setClass('Service')
			->setInject(TRUE);
	}
}


$compiler = new DI\Compiler;
$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
$compiler->addExtension('last', new LastExtension);
$container = createContainer($compiler, '
services:
	- stdClass
	two:
		class: Service
		inject: true
		setup:
		- injectB(1)
');


$builder = $compiler->getContainerBuilder();

Assert::equal( [
	new Statement(['@self', 'injectB']),
	new Statement(['@self', 'injectA']),
	new Statement(['@self', 'injectD']),
	new Statement(['@self', 'injectC']),
	new Statement(['@self', '$a'], ['@\\stdClass']),
	new Statement(['@self', '$c'], ['@\\stdClass']),
], $builder->getDefinition('one')->getSetup() );

Assert::equal( [
	new Statement(['@self', 'injectB'], [1]),
	new Statement(['@self', 'injectA']),
	new Statement(['@self', 'injectD']),
	new Statement(['@self', 'injectC']),
	new Statement(['@self', '$a'], ['@\\stdClass']),
	new Statement(['@self', '$c'], ['@\\stdClass']),
], $builder->getDefinition('two')->getSetup() );
