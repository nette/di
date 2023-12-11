<?php

/**
 * Test: Nette\DI\Compiler: inject.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


abstract class AbstractDependency
{
}

class ConcreteDependencyA extends AbstractDependency
{
}

class ConcreteDependencyB extends AbstractDependency
{
}



class ParentClass
{
	/** @var stdClass @inject */
	public $a;


	public function injectA()
	{
	}


	public function injectB()
	{
	}
}

class Service extends ParentClass
{
	/** @var stdClass @inject */
	public $c;

	/** @var AbstractDependency @inject */
	public $e;


	public function injectC()
	{
	}


	public function injectD()
	{
	}
}



class LastExtension extends DI\CompilerExtension
{
	private $param;


	public function beforeCompile()
	{
		// note that services should be added in loadConfiguration()
		$this->getContainerBuilder()->addDefinition($this->prefix('one'))
			->setType(Service::class)
			->addSetup('$e', ['@\ConcreteDependencyA'])
			->addTag(Nette\DI\Extensions\InjectExtension::TagInject);
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
	std: stdClass
	a: ConcreteDependencyA
	b: ConcreteDependencyB
	two:
		create: Service
		inject: true
		setup:
		- injectB(1)
		- @self::injectC(1)
		- $e(@\ConcreteDependencyB)
');


$builder = $compiler->getContainerBuilder();

Assert::equal([
	new Statement([new Reference('self'), 'injectA']),
	new Statement([new Reference('self'), 'injectB']),
	new Statement([new Reference('self'), 'injectC']),
	new Statement([new Reference('self'), 'injectD']),
	new Statement([new Reference('self'), '$e'], [new Reference('a')]),
	new Statement([new Reference('self'), '$c'], [new Reference('std')]),
	new Statement([new Reference('self'), '$a'], [new Reference('std')]),
], $builder->getDefinition('last.one')->getSetup());

Assert::equal([
	new Statement([new Reference('self'), 'injectA']),
	new Statement([new Reference('self'), 'injectB']),
	new Statement([new Reference('self'), 'injectC']),
	new Statement([new Reference('self'), 'injectD']),
	new Statement([new Reference('self'), '$e'], [new Reference('a')]),
	new Statement([new Reference('self'), '$c'], [new Reference('std')]),
	new Statement([new Reference('self'), '$a'], [new Reference('std')]),
], $builder->getDefinition('ext.one')->getSetup());

Assert::equal([
	new Statement([new Reference('self'), 'injectA']),
	new Statement([new Reference('self'), 'injectB'], [1]),
	new Statement([new Reference('self'), 'injectC'], [1]),
	new Statement([new Reference('self'), 'injectD']),
	new Statement([new Reference('self'), '$e'], [new Reference('b')]),
	new Statement([new Reference('self'), '$c'], [new Reference('std')]),
	new Statement([new Reference('self'), '$a'], [new Reference('std')]),
], $builder->getDefinition('two')->getSetup());
