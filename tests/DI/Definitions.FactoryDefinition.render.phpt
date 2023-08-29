<?php

/**
 * Test: FactoryDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\FactoryDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Good2
{
	public function create(): stdClass;
}


test('', function () {
	$def = new FactoryDefinition;
	$def->setName('abc');
	$def->setImplement(Good2::class);

	$builder = new Nette\DI\ContainerBuilder;
	$resolver = new Nette\DI\Resolver($builder);

	$resolver->resolveDefinition($def);
	$resolver->completeDefinition($def);

	$phpGenerator = new Nette\DI\PhpGenerator($builder);
	$method = $phpGenerator->generateMethod($def);

	Assert::match(
		'public function createServiceAbc(): Good2
{
	return new class ($this) implements Good2 {
		private $container;


		public function __construct($container)
		{
			$this->container = $container;
		}


		public function create(): stdClass
		{
			return new stdClass;
		}
	};
}',
		$method->__toString()
	);
});
