<?php

/**
 * Test: Nette\DI\ContainerBuilder code generator.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
	public $methods;


	/** @return Service */
	public static function create(DI\Container $container = null)
	{
		return new self(array_slice(func_get_args(), 1));
	}


	public function __construct($arg = null)
	{
		$this->methods[] = [__FUNCTION__, func_get_args()];
	}


	public function __call($nm, $args)
	{
		$this->methods[] = [$nm, $args];
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setClass('Service', ['@@string']);
$builder->addDefinition('three')
	->setClass('Service', ['a', 'b']);

$builder->addDefinition('four')
	->setClass('Service', ['a', 'b'])
	->addSetup('methodA', ['a', 'b'])
	->addSetup('@four::methodB', [1, 2])
	->addSetup('methodC', ['@self', '@container'])
	->addSetup('methodD', ['@one']);

$builder->addDefinition('five', null)
	->setFactory('Service::create');

$six = $builder->addDefinition('six')
	->setFactory('Service::create', ['@container', 'a', 'b'])
	->addSetup(['@six', 'methodA'], ['a', 'b']);

$builder->addDefinition('seven')
	->setFactory([$six, 'create'], ['@container', $six])
	->addSetup([$six, 'methodA'])
	->addSetup('$service->methodA(?)', ['a']);


$container = createContainer($builder);

Assert::type(Service::class, $container->getService('one'));
Assert::false($container->hasService('One'));
Assert::false($container->hasService('oNe'));

Assert::same([
	['__construct', ['@string']],
], $container->getService('one')->methods);

Assert::type(Service::class, $container->getService('three'));
Assert::same([
	['__construct', ['a', 'b']],
], $container->getService('three')->methods);

Assert::type(Service::class, $container->getService('four'));
Assert::same([
	['__construct', ['a', 'b']],
	['methodA', ['a', 'b']],
	['methodB', [1, 2]],
	['methodC', [$container->getService('four'), $container]],
	['methodD', [$container->getService('one')]],
], $container->getService('four')->methods);

Assert::type(Service::class, $container->getService('five'));
Assert::same([
	['__construct', [[]]],
], $container->getService('five')->methods);

Assert::type(Service::class, $container->getService('six'));
Assert::same([
	['__construct', [['a', 'b']]],
	['methodA', ['a', 'b']],
], $container->getService('six')->methods);

Assert::type(Service::class, $container->getService('seven'));
Assert::same([
	['__construct', [['a', 'b']]],
	['methodA', ['a', 'b']],
	['methodA', []],
], $container->getService('six')->methods);

Assert::same([
	['__construct', [[$container->getService('six')]]],
	['methodA', ['a']],
], $container->getService('seven')->methods);
