<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
	public $arg;


	public function __construct($arg)
	{
		$this->arg = $arg;
	}
}


test(function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: ::trim(" a ")

	services:
		one: Service(%bar%)
	');
	Assert::same('a', $container->getService('one')->arg);
});


test(function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: @two

	services:
		one: Service(%bar%)
		two: Service(two)
	');
	Assert::same($container->getService('two'), $container->getService('one')->arg);
});


test(function () {
	$compiler = new DI\Compiler;
	$container = createContainer($compiler, '
	parameters:
		bar: typed(Service)

	services:
		one: Service(%bar%)
		two: Service(two)
	');
	Assert::same([$container->getService('two')], $container->getService('one')->arg);
});
