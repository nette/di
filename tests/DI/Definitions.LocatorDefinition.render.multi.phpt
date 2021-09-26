<?php

/**
 * Test: LocatorDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\LocatorDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Good
{
	public function createFirst(): stdClass;

	public function getSecond(): ?stdClass;
}


test('', function () {
	$def = new LocatorDefinition;
	$def->setName('abc');
	$def->setImplement(Good::class);
	$def->setReferences(['first' => '@a', 'second' => 'stdClass']);

	$builder = new Nette\DI\ContainerBuilder;
	$builder->addDefinition('a')->setType(stdClass::class);
	$resolver = new Nette\DI\Resolver($builder);

	$resolver->resolveDefinition($def);
	$resolver->completeDefinition($def);

	$phpGenerator = new Nette\DI\PhpGenerator($builder);
	$method = $phpGenerator->generateMethod($def);

	Assert::match(
		<<<'XX'
public function createServiceAbc(): Good
{
	return new class ($this) implements Good {
		private $container;


		public function __construct($container)
		{
			$this->container = $container;
		}


		public function createFirst(): stdClass
		{
			return $this->container->createServiceA();
		}


		public function getSecond(): ?stdClass
		{
			return $this->container->getService('a');
		}
	};
}
XX
,
		$method->__toString()
	);
});
