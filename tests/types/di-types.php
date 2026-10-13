<?php declare(strict_types=1);

/**
 * PHPStan type tests.
 */

use Nette\DI\{Compiler, Container, ContainerBuilder, Definitions};
use Nette\DI\Extensions\DIExtension;
use function PHPStan\Testing\assertType;


class TestService
{
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
