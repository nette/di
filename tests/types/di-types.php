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
	assertType(Definitions\ServiceDefinition::class, $serviceDef);

	$accessorDef = $builder->addDefinition('bar', new Definitions\AccessorDefinition);
	assertType(Definitions\AccessorDefinition::class, $accessorDef);

	$factoryDef = $builder->addDefinition('baz', new Definitions\FactoryDefinition);
	assertType(Definitions\FactoryDefinition::class, $factoryDef);
}


function testReferenceNarrowing(Definitions\Reference $ref): void
{
	assertType('string', $ref->getValue());

	if ($ref->isType()) {
		assertType('class-string', $ref->getValue());
	} elseif ($ref->isName()) {
		assertType('string', $ref->getValue());
	} elseif ($ref->isSelf()) {
		assertType("'self'", $ref->getValue());
	}

	if ($ref->isName()) {
		assertType('string', $ref->getValue());
	}

	if ($ref->isSelf()) {
		assertType("'self'", $ref->getValue());
	}

	if (!$ref->isType()) {
		assertType('string', $ref->getValue());
	}

	if (!$ref->isName()) {
		assertType('string', $ref->getValue());
	}

	if (!$ref->isSelf()) {
		assertType('string', $ref->getValue());
	}
}


function testContainerBuilderGetOf(ContainerBuilder $builder): void
{
	// default: ServiceDefinition
	assertType(Definitions\ServiceDefinition::class, $builder->get('foo'));
	assertType(Definitions\ServiceDefinition::class, $builder->get(type: TestService::class));

	// of: addresses another definition class
	assertType(Definitions\FactoryDefinition::class, $builder->get('foo', of: Definitions\FactoryDefinition::class));

	// find: default Definition, of: narrows
	assertType('array<string, Nette\DI\Definition>', $builder->find(tag: 't'));
	assertType('array<string, Nette\DI\Definitions\ServiceDefinition>', $builder->find(tag: 't', of: Definitions\ServiceDefinition::class));
}
