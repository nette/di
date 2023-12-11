<?php

/**
 * Test: Nette\DI\ContainerBuilder and collection via typed().
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	public $arr;


	public function __construct(array $arr = [])
	{
		$this->arr = $arr;
	}
}

class Service
{
}

class ServiceChild extends Service
{
}


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	f1: Foo(typed(Service))
	f2: Foo(typed(ServiceChild, stdClass))
	f3: Foo(typed(Foo))
	s1: Service
	s2: Service
	s3: ServiceChild
	s4: stdClass
	s5:
		create: Service
		autowired: no
');

$foo = $container->getService('f1');
Assert::same([
	$container->getService('s1'),
	$container->getService('s2'),
	$container->getService('s3'),
], $foo->arr);

$foo = $container->getService('f2');
Assert::same([
	$container->getService('s3'),
	$container->getService('s4'),
], $foo->arr);

$foo = $container->getService('f3');
Assert::same([
	$container->getService('f1'),
	$container->getService('f2'),
], $foo->arr);
