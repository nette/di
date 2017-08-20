<?php

/**
 * Test: Nette\DI\ContainerBuilder and rich syntax.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
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
	/** @return Obj */
	public function foo(...$args)
	{
		$this->args[] = $args;
		return $this;
	}
}


$builder = new DI\ContainerBuilder;
$one = $builder->addDefinition('one')
	->setFactory([new DI\Statement('Factory'), 'create'])
	->addSetup([new DI\Statement('Factory'), 'mark'], ['@self']);

$two = $builder->addDefinition('two')
	->setFactory([new DI\Statement([$one, 'foo'], [1]), 'foo'], [2]);


$container = createContainer($builder);

Assert::same('Obj', $one->getType());
Assert::type(Obj::class, $container->getService('one'));
Assert::true($container->getService('one')->mark);

Assert::same('Obj', $two->getType());
Assert::type(Obj::class, $container->getService('two'));
Assert::true($container->getService('two')->mark);
Assert::same([[1], [2]], $container->getService('two')->args);
