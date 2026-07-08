<?php declare(strict_types=1);

/**
 * Test: Nette\DI: static factory method with a private constructor.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	private function __construct()
	{
	}


	public static function create(): self
	{
		return new self;
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setCreator('Factory::create');


$container = createContainer($builder);

Assert::type(Factory::class, $container->getService('one'));
