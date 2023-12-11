<?php

/**
 * Test: Nette\DI\ContainerBuilder and collection via tagged().
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


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	f1: Foo(tagged(first))
	f2: Foo(tagged(second, third))
	s1:
		create: Service
		tags: [first]
	s2:
		create: Service
		tags: [first]
	s3:
		create: Service
		tags: [first: false]
	s4:
		create: Service
		tags: [first: null]
	s5:
		create: Service
		tags: [second]
	s6:
		create: Service
		tags: [third]
');

$foo = $container->getService('f1');
Assert::same([
	$container->getService('s1'),
	$container->getService('s2'),
	$container->getService('s3'),
], $foo->arr);

$foo = $container->getService('f2');
Assert::same([
	$container->getService('s5'),
	$container->getService('s6'),
], $foo->arr);
