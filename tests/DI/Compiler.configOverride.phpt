<?php

/**
 * Test: Overriding/modifying service definition in another config
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Ipsum
{
	public function __construct(...$args)
	{
		Notes::add(__METHOD__ . ' ' . implode(' ', $args));
	}
}

$class = 'Container';
$compiler = new DI\Compiler;
$compiler->addConfig([
	'services' => [
		's1' => Ipsum::class,
		's2' => ['type' => Ipsum::class],
	],
]);
$compiler->addConfig([
	'services' => [
		's1' => ['arguments' => [2]],
		's2' => ['type' => Ipsum::class, 'alteration' => true],
	],
]);

$code = $compiler->setClassName($class)
	->compile();

file_put_contents(getTempDir() . '/code.php', "<?php\n\n$code");
require getTempDir() . '/code.php';

$container = new $class;
assert($container instanceof DI\Container);

Assert::type(Ipsum::class, $container->getService('s1'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

$compiler->addConfig([
	'services' => [
		's3' => ['type' => Ipsum::class, 'alteration' => true],
	],
]);

Assert::exception(
	fn() => $compiler->setClassName($class)->compile(),
	DI\InvalidConfigurationException::class,
	"Service 's3': missing original definition for alteration.",
);
