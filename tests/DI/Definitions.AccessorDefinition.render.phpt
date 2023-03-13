<?php

/**
 * Test: AccessorDefinition
 */

declare(strict_types=1);

use Nette\DI\Definitions\AccessorDefinition;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface Good2
{
	public function get(): stdClass;
}


test('', function () {
	$def = new AccessorDefinition;
	$def->setName('abc');
	$def->setImplement(Good2::class);

	$builder = new Nette\DI\ContainerBuilder;
	$builder->addDefinition('a')->setType(stdClass::class);
	$resolver = new Nette\DI\Resolver($builder);

	$resolver->resolveDefinition($def);
	$resolver->completeDefinition($def);

	$phpGenerator = new Nette\DI\PhpGenerator($builder);
	$method = $phpGenerator->generateMethod($def);

	Assert::match(
		<<<'XX'
			public function createServiceAbc(): Good2
			{
				return new class ($this) implements Good2 {
					public function __construct(
						private $container,
					) {
					}


					public function get(): stdClass
					{
						return $this->container->getService('a');
					}
				};
			}
			XX,
		$method->__toString(),
	);
});
