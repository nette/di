<?php declare(strict_types=1);

/**
 * Test: Nette\DI\ContainerBuilder and services passed to a variadic parameter.
 */

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


interface Plugin
{
}

class PluginA implements Plugin
{
}

class PluginB implements Plugin
{
}

class Host
{
	public array $plugins;


	public function __construct(Plugin ...$plugins)
	{
		$this->plugins = $plugins;
	}
}


test('a variadic parameter is not autowired', function () {
	$container = createContainer(new DI\Compiler, '
services:
	- PluginA
	- PluginB
	host: Host
');

	Assert::same([], $container->getService('host')->plugins);
});


test('typed() passes all services of a type to a variadic parameter', function () {
	$container = createContainer(new DI\Compiler, '
services:
	- PluginA
	- PluginB
	host: Host(plugins: typed(Plugin))
');

	$plugins = $container->getService('host')->plugins;
	Assert::count(2, $plugins);
	Assert::type(PluginA::class, $plugins[0]);
	Assert::type(PluginB::class, $plugins[1]);
});
