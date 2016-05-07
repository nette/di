<?php

/**
 * Test: Nette\DI\Compiler: inject.
 */

use Nette\DI;
use Nette\DI\Statement;
use Tester\Assert;


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
		$this->getContainerBuilder()->addDefinition($this->prefix('one'))
			->setClass('Service')
			->setInject(TRUE);
	}
}


$compiler = new DI\Compiler;
$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
$compiler->addExtension('extensions', new Nette\DI\Extensions\ExtensionsExtension);
$compiler->addExtension('last', new LastExtension);
$container = createContainer($compiler, '
extensions:
	ext: LastExtension

services:
	- stdClass
	two:
		class: Service
		inject: true
		setup:
		- injectB(1)
');


$builder = $compiler->getContainerBuilder();

Assert::equal([
	new Statement('injectB'),
	new Statement('injectA'),
	new Statement('injectD'),
	new Statement('injectC'),
	new Statement('$c', ['@\\stdClass']),
	new Statement('$a', ['@\\stdClass']),
], $builder->getDefinition('last.one')->getSetup());

Assert::equal([
	new Statement('injectB'),
	new Statement('injectA'),
	new Statement('injectD'),
	new Statement('injectC'),
	new Statement('$c', ['@\\stdClass']),
	new Statement('$a', ['@\\stdClass']),
], $builder->getDefinition('ext.one')->getSetup());

Assert::equal([
	new Statement('injectB', [1]),
	new Statement('injectA'),
	new Statement('injectD'),
	new Statement('injectC'),
	new Statement('$c', ['@\\stdClass']),
	new Statement('$a', ['@\\stdClass']),
], $builder->getDefinition('two')->getSetup());
