<?php

/**
 * Test: Nette\DI\Compiler: multiple service inhertance
 * @package    Nette\DI
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';



class BaseService
{
	private $private;

	function setPrivate($private) {
		$this->private = $private;
	}

	function getPrivate() {
		return $this->private;
	}
}


class ChildService extends BaseService
{}


class SubChildService extends ChildService
{}


class SecondChildService extends ChildService
{}



define('PRIVATE_VALUE', 'foo.bar');


$compiler = new DI\Compiler;
$compiler->getContainerBuilder()->addDefinition('outer')->setClass('stdClass');

$container = createContainer($compiler, '
services:
	subchild < child:
		factory: SubChildService()

	base:
		factory: BaseService()
		setup:
			- setPrivate( ::PRIVATE_VALUE )

	child < base:
		factory: ChildService()

	secchild < child:
		factory: SecondChildService()

	outerchild < outer:
');

Assert::same(PRIVATE_VALUE, $container->getService('base')->getPrivate());
Assert::same(PRIVATE_VALUE, $container->getService('child')->getPrivate());
Assert::same(PRIVATE_VALUE, $container->getService('subchild')->getPrivate());
Assert::same(PRIVATE_VALUE, $container->getService('secchild')->getPrivate());
