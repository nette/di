<?php declare(strict_types=1);

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


class Sensitive
{
	public function __construct(
		#[SensitiveParameter]
		public $value,
	) {
	}


	public function echo(
		#[SensitiveParameter]
		$value,
	) {
		return $value;
	}
}


$builder = new DI\ContainerBuilder;
$builder->addDefinition('one')
	->setType(stdClass::class);


$container = createContainer($builder);

$test = $container->createInstance(Test::class);
Assert::type(Test::class, $test);
Assert::same($container, $test->container);
Assert::false($container->callMethod([$test, 'method']));
Assert::true($container->callMethod([$test, 'method'], [(object) ['prop' => true]]));
$sensitive = $container->createInstance(Sensitive::class, ['secret']);
Assert::same('secret', $sensitive->value);
Assert::same('secret', $container->callMethod([$sensitive, 'echo'], ['secret']));
