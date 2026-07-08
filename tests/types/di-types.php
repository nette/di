<?php declare(strict_types=1);

/**
 * PHPStan type tests.
 */

use Nette\DI;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions;
use Nette\DI\Extensions\DIExtension;
use function PHPStan\Testing\assertType;


class TestService
{
}

interface TestFactory
{
	public function create(): TestService;
}

interface TestAccessor
{
	public function get(): TestService;
}


function testContainerBuilderAdd(ContainerBuilder $builder): void
{
	// creator = class-string or expression -> ServiceDefinition (even after fluent narrowing)
	assertType('Nette\DI\Definitions\ServiceDefinition', $builder->add('a', TestService::class));
	assertType('Nette\DI\Definitions\ServiceDefinition', $builder->add('b', di\create(TestService::class)));
	assertType('Nette\DI\Definitions\ServiceDefinition', $builder->add('c', di\create(TestService::class))->setType(TestService::class));
	assertType('Nette\DI\Definitions\FactoryDefinition', $builder->add('d', di\factory(TestFactory::class)));
	assertType('Nette\DI\Definitions\AccessorDefinition', $builder->add('e', di\accessor(TestAccessor::class)));
	assertType('Nette\DI\Definitions\ImportedDefinition', $builder->add('f', di\imported(TestService::class)));
	assertType('Nette\DI\Definitions\ServiceDefinition', $builder->add('g', di\factory(TestFactory::class))->getResultDefinition());
}


function testContainerGetByType(Container $container): void
{
	$service = $container->getByType(TestService::class);
	assertType(TestService::class, $service);

	$serviceOrNull = $container->getByType(TestService::class, throw: false);
	assertType(TestService::class . '|null', $serviceOrNull);
}


function testContainerCreateInstance(Container $container): void
{
	$service = $container->createInstance(TestService::class);
	assertType(TestService::class, $service);
}


function testCompilerGetExtensions(Compiler $compiler): void
{
	$all = $compiler->getExtensions();
	assertType('array<string, Nette\DI\CompilerExtension>', $all);

	$diExts = $compiler->getExtensions(DIExtension::class);
	assertType('array<string, Nette\DI\Extensions\DIExtension>', $diExts);
}


function testContainerBuilderGetByType(ContainerBuilder $builder): void
{
	$name = $builder->getByType(TestService::class, throw: true);
	assertType('string', $name);

	$nameOrNull = $builder->getByType(TestService::class);
	assertType('string|null', $nameOrNull);
}


function testContainerBuilderAddDefinition(ContainerBuilder $builder): void
{
	$serviceDef = $builder->addDefinition('foo');
	assertType('Nette\DI\Definitions\ServiceDefinition', $serviceDef);

	$accessorDef = $builder->addDefinition('bar', new Definitions\AccessorDefinition);
	assertType('Nette\DI\Definitions\AccessorDefinition', $accessorDef);

	$factoryDef = $builder->addDefinition('baz', new Definitions\FactoryDefinition);
	assertType('Nette\DI\Definitions\FactoryDefinition', $factoryDef);
}


function testContainerBuilderGetOf(ContainerBuilder $builder): void
{
	// default: ServiceDefinition
	assertType('Nette\DI\Definitions\ServiceDefinition', $builder->get('foo'));
	assertType('Nette\DI\Definitions\ServiceDefinition', $builder->get(type: TestService::class));

	// of: addresses another definition class
	assertType('Nette\DI\Definitions\FactoryDefinition', $builder->get('foo', of: Definitions\FactoryDefinition::class));

	// find: default Definition, of: narrows
	assertType('array<string, Nette\DI\Definition>', $builder->find(tag: 't'));
	assertType('array<string, Nette\DI\Definitions\ServiceDefinition>', $builder->find(tag: 't', of: Definitions\ServiceDefinition::class));
}


function testDefinitionsInterfaceTyping(Nette\DI\Definitions $di): void
{
	// of: generics work the same through the interface as on the builder
	assertType('Nette\DI\Definitions\ServiceDefinition', $di->get('foo'));
	assertType('Nette\DI\Definitions\FactoryDefinition', $di->get('foo', of: Definitions\FactoryDefinition::class));
	assertType('array<string, Nette\DI\Definition>', $di->find(tag: 't'));
	assertType('array<string, Nette\DI\Definitions\ServiceDefinition>', $di->find(tag: 't', of: Definitions\ServiceDefinition::class));
}
