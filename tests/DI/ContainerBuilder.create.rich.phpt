<?php

/**
 * Test: Nette\DI\ContainerBuilder and rich syntax.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Statement;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	public $mark;


	/** @return Obj */
	public function create()
	{
		return new Obj;
	}


	public function mark(Obj $obj)
	{
		$obj->mark = true;
	}
}

class Obj
{
	public $args;
	public $mark;


	/** @return Obj */
	public function foo(...$args)
	{
		$this->args[] = $args;
		return $this;
	}
}


$builder = new DI\ContainerBuilder;
$one = $builder->addDefinition('one')
	->setFactory([new Statement(Factory::class), 'create'])
	->addSetup([new Statement(Factory::class), 'mark'], ['@self']);

$two = $builder->addDefinition('two')
	->setFactory([new Statement([$one, 'foo'], [1]), 'foo'], [2]);


$container = createContainer($builder);

Assert::same(Obj::class, $one->getType());
Assert::type(Obj::class, $container->getService('one'));
Assert::true($container->getService('one')->mark);

Assert::same(Obj::class, $two->getType());
Assert::type(Obj::class, $container->getService('two'));
Assert::true($container->getService('two')->mark);
Assert::same([[1], [2]], $container->getService('two')->args);
