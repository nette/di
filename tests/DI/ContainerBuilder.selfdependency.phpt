<?php

/**
 * Test: Nette\DI\ContainerBuilder and self-dependency.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{
	public function __construct(self $foo)
	{
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition(null)
	->setCreator(Foo::class);

Assert::exception(function () use ($builder) {
	createContainer($builder);
}, Nette\DI\ServiceCreationException::class, '[Service of type Foo]
Service of type Foo required by $foo in Foo::__construct() not found.
Did you add it to configuration file?');
