<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Compiler: inject.
 */

use Nette\DI;
use Nette\DI\Attributes\Inject;
use Nette\DI\Expressions\Call;
use Nette\DI\Expressions\PropertyAccess;
use Nette\DI\Expressions\PropertyMode;
use Nette\DI\Expressions\Reference;
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
	#[Inject]
	public stdClass $a;


	public function injectA()
	{
	}


	public function injectB()
	{
	}
}

class Service extends ParentClass
{
	#[Inject]
	public stdClass $c;

	#[Inject]
	public AbstractDependency $e;


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
	new Call(new Reference('self'), 'injectA'),
	new Call(new Reference('self'), 'injectB'),
	new Call(new Reference('self'), 'injectC'),
	new Call(new Reference('self'), 'injectD'),
	new PropertyAccess(new Reference('self'), 'e', PropertyMode::Assign, new Reference('a')),
	new PropertyAccess(new Reference('self'), 'c', PropertyMode::Assign, new Reference('std')),
	new PropertyAccess(new Reference('self'), 'a', PropertyMode::Assign, new Reference('std')),
], $builder->getDefinition('last.one')->getSetup());

Assert::equal([
	new Call(new Reference('self'), 'injectA'),
	new Call(new Reference('self'), 'injectB'),
	new Call(new Reference('self'), 'injectC'),
	new Call(new Reference('self'), 'injectD'),
	new PropertyAccess(new Reference('self'), 'e', PropertyMode::Assign, new Reference('a')),
	new PropertyAccess(new Reference('self'), 'c', PropertyMode::Assign, new Reference('std')),
	new PropertyAccess(new Reference('self'), 'a', PropertyMode::Assign, new Reference('std')),
], $builder->getDefinition('ext.one')->getSetup());

Assert::equal([
	new Call(new Reference('self'), 'injectA'),
	new Call(new Reference('self'), 'injectB', [1]),
	new Call(new Reference('self'), 'injectC', [1]),
	new Call(new Reference('self'), 'injectD'),
	new PropertyAccess(new Reference('self'), 'e', PropertyMode::Assign, new Reference('b')),
	new PropertyAccess(new Reference('self'), 'c', PropertyMode::Assign, new Reference('std')),
	new PropertyAccess(new Reference('self'), 'a', PropertyMode::Assign, new Reference('std')),
], $builder->getDefinition('two')->getSetup());
