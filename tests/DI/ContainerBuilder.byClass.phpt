<?php

/**
 * Test: Nette\DI\ContainerBuilder code generator.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IFactory
{
	public static function create();
}

class Factory implements IFactory
{
	public static $methods;


	public static function create()
	{
		self::$methods[] = [__FUNCTION__, func_get_args()];
		return new stdClass;
	}
}

class AnnotatedFactory
{
	public $methods;


	public function create(): stdClass
	{
		$this->methods[] = [__FUNCTION__, func_get_args()];
		return new stdClass;
	}
}


class UninstantiableFactory
{
	public static function getInstance()
	{
		return new self;
	}


	private function __construct()
	{
	}


	public function create(): stdClass
	{
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('factory')
	->setType(Factory::class);

$builder->addDefinition('annotatedFactory')
	->setType(AnnotatedFactory::class);

$builder->addDefinition('two')
	->setType(stdClass::class)
	->setAutowired(false)
	->setFactory('@factory::create', ['@\Factory'])
	->addSetup(['@\Factory', 'create'], ['@\Factory']);

$builder->addDefinition('three')
	->setType(stdClass::class)
	->setAutowired(false)
	->setFactory('@\Factory::create', ['@\Factory']);

$builder->addDefinition('four')
	->setAutowired(false)
	->setFactory('@\AnnotatedFactory::create');

$builder->addDefinition('five')
	->setType(stdClass::class)
	->setAutowired(false)
	->setFactory('@\IFactory::create');

$builder->addDefinition('uninstantiableFactory')
	->setType(UninstantiableFactory::class)
	->setFactory('UninstantiableFactory::getInstance');

$builder->addDefinition('six')
	->setAutowired(false)
	->setFactory('@\UninstantiableFactory::create');



$container = createContainer($builder);

$factory = $container->getService('factory');
Assert::type(Factory::class, $factory);

Assert::type(stdClass::class, $container->getService('two'));
Assert::same([
	['create', [$factory]],
	['create', [$factory]],
], Factory::$methods);

Factory::$methods = null;

Assert::type(stdClass::class, $container->getService('three'));
Assert::same([
	['create', [$factory]],
], Factory::$methods);

$annotatedFactory = $container->getService('annotatedFactory');
Assert::type(AnnotatedFactory::class, $annotatedFactory);

Assert::type(stdClass::class, $container->getService('four'));
Assert::same([
	['create', []],
], $annotatedFactory->methods);

Assert::type(stdClass::class, $container->getService('five'));
