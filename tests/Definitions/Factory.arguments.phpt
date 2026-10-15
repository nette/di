<?php declare(strict_types=1);

/**
 * Test: Nette\DI\ContainerBuilder and generated factories with arguments.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Foo
{
	public function __construct(
		public $value,
	) {
	}
}

interface FooFactory
{
	function create(): Foo;
}


$builder = new DI\ContainerBuilder;
$builder->addFactoryDefinition('fooFactory')
	->setImplement(FooFactory::class)
	->getResultDefinition()
		->setArguments(['bar']);


$container = createContainer($builder);

Assert::type(FooFactory::class, $container->getService('fooFactory'));
Assert::type(Foo::class, $foo = $container->getService('fooFactory')->create());
Assert::same('bar', $foo->value);
