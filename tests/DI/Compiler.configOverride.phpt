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
	function __construct(...$args)
	{
		Notes::add(__METHOD__ . ' ' . implode(' ' , $args));
	}
}

$class = 'Container' . md5((string) lcg_value());
$compiler = new DI\Compiler;
$compiler->addConfig([
	'services' => [
		's1' => 'Ipsum',
		's2' => ['class' => 'Ipsum'],
	],
]);
$compiler->addConfig([
	'services' => [
		's1' => ['arguments' => [2]],
		's2' => ['class' => 'Ipsum', 'alteration' => TRUE,],
	],
]);

$code = $compiler->setClassName($class)
	->compile();

file_put_contents(TEMP_DIR . '/code.php', "<?php\n\n$code");
require TEMP_DIR . '/code.php';

/** @var DI\Container $container */
$container = new $class();

Assert::type(Ipsum::class, $container->getService('s1'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

$compiler->addConfig([
	'services' => [
		's3' => ['class' => 'Ipsum', 'alteration' => TRUE,],
	],
]);

Assert::exception(function () use ($compiler, $class) {
	$compiler->setClassName($class)
		->compile();
}, DI\ServiceCreationException::class, "Service 's3': missing original definition for alteration.");
