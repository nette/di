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
	public function get($name): stdClass;
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
					private $container;%A?%
					private $mapping = ['first' => 'a', 'second' => 'a'];


					public function __construct($container)
					{
						$this->container = $container;
					}


					public function get($name): stdClass
					{
						if (!isset($this->mapping[$name])) {
							throw new Nette\DI\MissingServiceException("Service '$name' is not defined.");
						}
						return $this->container->getService($this->mapping[$name]);
					}
				};
			}
			XX,
		$method->__toString(),
	);
});
