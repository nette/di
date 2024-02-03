<?php

/**
 * Test: Nette\DI\ContainerBuilder and class blacklist
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IFoo
{
}

interface IBar
{
}

class Foo implements IFoo
{
}

class Bar extends Foo implements IBar
{
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('bar')
		->setType(Bar::class);
$builder->addExcludedClasses([Foo::class, IBar::class]);


Assert::same('bar', $builder->getByType(Bar::class));
Assert::null($builder->getByType(IBar::class));
Assert::null($builder->getByType(Foo::class));
Assert::null($builder->getByType(IFoo::class));

Assert::same(['bar'], array_keys($builder->findAutowired(Bar::class)));
Assert::same([], array_keys($builder->findAutowired(IBar::class)));
Assert::same([], array_keys($builder->findAutowired(Foo::class)));
Assert::same([], array_keys($builder->findAutowired(IFoo::class)));

Assert::same(['bar'], array_keys($builder->findByType(Bar::class)));
Assert::same(['bar'], array_keys($builder->findByType(IBar::class)));
Assert::same(['bar'], array_keys($builder->findByType(Foo::class)));
Assert::same(['bar'], array_keys($builder->findByType(IFoo::class)));


$container = createContainer($builder);

Assert::type(Bar::class, $container->getByType(Bar::class));

Assert::exception(
	fn() => $container->getByType(IBar::class),
	DI\MissingServiceException::class,
);

Assert::exception(
	fn() => $container->getByType(Foo::class),
	DI\MissingServiceException::class,
);

Assert::exception(
	fn() => $container->getByType(IFoo::class),
	DI\MissingServiceException::class,
);

Assert::same(['bar'], $container->findByType(Bar::class));
Assert::same(['bar'], $container->findByType(IBar::class));
Assert::same(['bar'], $container->findByType(Foo::class));
Assert::same(['bar'], $container->findByType(IFoo::class));
