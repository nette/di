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
		Assert::same(['foo' => 'hello'], $this->config);
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


Assert::same([
	'DatabaseExtension::loadConfiguration',
	'DatabaseExtension::beforeCompile',
	'DatabaseExtension::afterCompile',
], Notes::fetch());


Assert::same('database.', $extension->prefix(''));
Assert::same('database.member', $extension->prefix('member'));
Assert::same('@database.member', $extension->prefix('@member'));


Assert::same(['foo' => 'hello'], $extension->getConfig());
