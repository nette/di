<?php

/**
 * Test: Nette\DI\Compiler: inject.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Attributes\Inject;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Dependency
{
}


class DependencyA implements Dependency
{
}


class DependencyB implements Dependency
{
}


class Service
{
	#[Inject] public Dependency $dependencyD;

	#[Inject(tag: 'alt')] public Dependency $dependencyE;

	#[Inject(tag: 'default')] public Dependency $dependencyF;

	private Dependency $injectedA;
	private Dependency $injectedB;


	public function __construct(
		public Dependency $dependencyA,
		#[Inject(tag: 'duplicate')]
		public Dependency $dependencyADuplicate,
		#[Inject(tag: 'alt')]
		public Dependency $dependencyB,
		#[Inject(tag: 'default')]
		public Dependency $dependencyC,
		private readonly Dependency $privateA,
		#[Inject(tag: 'alt')]
		private readonly Dependency $privateB,
	) {
	}


	public function injectDependencies(
		Dependency $injectedA,
		#[Inject(tag: 'alt')]
		Dependency $injectedB,
	) {
		$this->injectedA = $injectedA;
		$this->injectedB = $injectedB;
	}


	public function getInjectedA(): Dependency
	{
		return $this->injectedA;
	}


	public function getInjectedB(): Dependency
	{
		return $this->injectedB;
	}


	public function getPrivateA(): Dependency
	{
		return $this->privateA;
	}


	public function getPrivateB(): Dependency
	{
		return $this->privateB;
	}
}


$compiler = new DI\Compiler;
$compiler->addExtension('inject', new Nette\DI\Extensions\InjectExtension);
$container = createContainer($compiler, '
services:
	a:
		create: DependencyA
		tags:
			- default
	b:
		create: DependencyB
		tags:
			- alt
	c:
		create: DependencyA
		tags:
			- duplicate
	service:
		create: Service
		inject: true
');


$builder = $compiler->getContainerBuilder();

Assert::same(
	$builder->getByType(Dependency::class),
	'a',
);

Assert::same(
	$builder->getByTypeAndTag(Dependency::class, tag: 'alt'),
	'b',
);

Assert::same(
	$builder->getByTypeAndTag(Dependency::class, tag: 'duplicate'),
	'c',
);

Assert::same(
	$builder->getByTypeAndTag(Dependency::class, tag: 'default'),
	'a',
);


Assert::equal(
	$container->getByType(Service::class)->dependencyA,
	new DependencyA,
);

Assert::equal(
	$container->getByType(Service::class)->dependencyADuplicate,
	new DependencyA,
);

Assert::notSame(
	$container->getByType(Service::class)->dependencyA,
	$container->getByType(Service::class)->dependencyADuplicate,
);

Assert::same(
	$container->getByType(Service::class)->dependencyA,
	$container->getByType(Service::class)->dependencyC,
);

Assert::equal(
	$container->getByType(Service::class)->dependencyB,
	new DependencyB,
);

Assert::equal(
	$container->getByType(Service::class)->dependencyC,
	new DependencyA,
);

Assert::equal(
	$container->getByType(Service::class)->dependencyD,
	new DependencyA,
);

Assert::equal(
	$container->getByType(Service::class)->dependencyE,
	new DependencyB,
);

Assert::equal(
	$container->getByType(Service::class)->dependencyF,
	new DependencyA,
);

Assert::equal(
	$container->getByType(Service::class)->getInjectedA(),
	new DependencyA,
);

Assert::equal(
	$container->getByType(Service::class)->getInjectedB(),
	new DependencyB,
);

Assert::equal(
	$container->getByType(Service::class)->getPrivateA(),
	new DependencyA,
);

Assert::equal(
	$container->getByType(Service::class)->getPrivateB(),
	new DependencyB,
);


Assert::equal(
	$container->getByType(Dependency::class),
	new DependencyA,
);

Assert::equal(
	$container->getByTypeAndTag(Dependency::class, tag: 'alt'),
	new DependencyB,
);

Assert::equal(
	$container->getByTypeAndTag(Dependency::class, tag: 'default'),
	new DependencyA,
);

Assert::same(
	$container->findByType(Dependency::class),
	['a', 'b', 'c'],
);
