<?php

/**
 * Test: Nette\DI\ContainerBuilder.
 */

declare(strict_types=1);

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
	->setFactory('Factory::create');


$container = createContainer($builder);

Assert::type(Factory::class, $container->getService('one'));
