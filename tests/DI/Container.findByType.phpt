<?php

/**
 * Test: Nette\DI\Container: findByType behaviour.
 *
 * @author     Lukáš Unger
 */

use Nette\DI,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Lorem
{

}


$loader = new DI\Config\Loader;
$compiler = new DI\Compiler;
$code = $compiler->compile($loader->load(__DIR__ . '/files/container.findByType.neon'), 'Container', 'Nette\DI\Container');

file_put_contents(TEMP_DIR . '/code.php', "<?php\n\n$code");
require TEMP_DIR . '/code.php';

$container = new Container;


Assert::same(array('dolor'), $container->findByType('Lorem'));
Assert::same(array('dolor', 'lorem'), $container->findByType('Lorem', FALSE));
