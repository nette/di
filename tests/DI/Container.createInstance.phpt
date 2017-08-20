<?php

/**
 * Test: Nette\DI\ContainerBuilder and Container: createInstance()
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Test
{
	public $container;


	public function __construct(stdClass $obj, DI\Container $container)
	{
		$this->container = $container;
	}


	public function method(stdClass $obj, DI\Container $container)
	{
		return isset($obj->prop);
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType('stdClass');


$container = createContainer($builder);

$test = $container->createInstance('Test');
Assert::type(Test::class, $test);
Assert::same($container, $test->container);
Assert::false($container->callMethod([$test, 'method']));
Assert::true($container->callMethod([$test, 'method'], [(object) ['prop' => true]]));
