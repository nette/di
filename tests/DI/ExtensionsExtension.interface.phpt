<?php

/**
 * Test: Nette\DI\Compiler and ExtensionsExtension.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class FooExtension extends DI\CompilerExtension
{
	function loadConfiguration()
	{
		$this->getContainerBuilder()->parameters['registeredExtensions'] = array_keys($this->compiler->getExtensions());
		$this->getContainerBuilder()->parameters[$this->name] = $this->getConfig();
	}
}


class UppercaseExtensionExtension extends DI\CompilerExtension implements DI\Extensions\IExtensionsExtension
{
	function loadConfiguration()
	{
		foreach ($this->getConfig() as $name => $class) {
			$this->compiler->addExtension('__' . strtoupper($name), new $class);
		}
	}
}


$compiler = new DI\Compiler;
$compiler->addExtension('extensions', new Nette\DI\Extensions\ExtensionsExtension);
$compiler->addExtension('customExtensions', new UppercaseExtensionExtension);
$container = createContainer($compiler, '
extensions:
	foo: FooExtension

customExtensions:
	foo: FooExtension

foo:
	key: value

__FOO:
	KEY: VALUE
');


Assert::same([
	'registeredExtensions' => ['extensions', 'customExtensions', 'foo', '__FOO'],
	'foo' => ['key' => 'value'],
	'__FOO' => ['KEY' => 'VALUE'],
], $container->parameters);
