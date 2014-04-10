<?php

/**
 * Test: Nette\DI\ContainerBuilder: findByType()
 *
 * @author     Lukáš Unger
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service extends Nette\Object
{
}

class Service2 extends Nette\Object
{
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setClass('Service');
$builder->addDefinition('two')
	->setClass('Service2');


// compile-time
$builder->prepareClassList();

Assert::same(array('one'), $builder->findByType('service'));
Assert::same(array(), $builder->findByType('unknown'));
Assert::same(array('one', 'two'), $builder->findByType('Nette\Object'));
