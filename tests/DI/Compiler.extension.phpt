<?php

/**
 * Test: Nette\DI\Compiler and user extension.
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class DatabaseExtension extends Nette\DI\CompilerExtension
{

	public function loadConfiguration()
	{
		Assert::same(array('foo' => 'hello'), $this->config);
		Notes::add(__METHOD__);
	}

	public function beforeCompile()
	{
		Notes::add(__METHOD__);
	}

	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		Notes::add(__METHOD__);
	}
}

class FooExtension extends Nette\DI\CompilerExtension
{
}


$compiler = new DI\Compiler;
$extension = new DatabaseExtension;
$compiler->addExtension('database', $extension);
$compiler->addExtension('foo', new FooExtension);
$container = createContainer($compiler, '
parameters:
	bar: hello


database:
	foo: %bar%

foo:
');


Assert::same(array(
	'DatabaseExtension::loadConfiguration',
	'DatabaseExtension::beforeCompile',
	'DatabaseExtension::afterCompile',
), Notes::fetch());


Assert::same('database.', $extension->prefix(''));
Assert::same('database.member', $extension->prefix('member'));
Assert::same('@database.member', $extension->prefix('@member'));


Assert::same(array('foo' => 'hello'), $extension->getConfig());
Assert::same(array('foo' => 'hello'), $extension->getConfig(array('foo' => 'bar')));
Assert::same(array('foo2' => 'hello', 'foo' => 'hello'), $extension->getConfig(array('foo2' => '%bar%')));
