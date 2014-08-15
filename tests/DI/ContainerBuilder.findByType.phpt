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

class Service3 extends Nette\Object
{
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setClass('Service');
$builder->addDefinition('two')
	->setClass('Service2');
$builder->addDefinition('three')
	->setClass('Service3')
	->setAutowired(FALSE);


// compile-time
$builder->prepareClassList();

//autowired
Assert::same(array('one'), $builder->findByType('service'));
Assert::same(array(), $builder->findByType('unknown'));
Assert::same(array(), $builder->findByType('service3'));
Assert::same(array('one', 'two'), $builder->findByType('Nette\Object'));

//all
Assert::same(array('one'), $builder->findByType('service', FALSE));
Assert::same(array(), $builder->findByType('unknown', FALSE));
Assert::same(array('three'), $builder->findByType('service3', FALSE));
Assert::same(array('one', 'two', 'three'), $builder->findByType('Nette\Object', FALSE));
