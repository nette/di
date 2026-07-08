<?php declare(strict_types=1);

/**
 * Test: Nette\DI\CompilerExtension: lifecycle (loadConfiguration, beforeCompile, afterCompile).
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class DatabaseExtension extends Nette\DI\CompilerExtension
{
	public array $calledMethods = [];


	public function loadConfiguration()
	{
		Assert::same(['foo' => 'hello'], $this->config);
		$this->calledMethods[] = __METHOD__;
	}


	public function beforeCompile()
	{
		$this->calledMethods[] = __METHOD__;
	}


	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		$this->calledMethods[] = __METHOD__;
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
], $extension->calledMethods);


Assert::same('database.', $extension->prefix(''));
Assert::same('database.member', $extension->prefix('member'));
Assert::same('@database.member', $extension->prefix('@member'));


Assert::same(['foo' => 'hello'], $extension->getConfig());
