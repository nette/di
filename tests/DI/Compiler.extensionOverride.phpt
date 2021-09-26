<?php

/**
 * Test: Overriding class of service definition defined in CompilerExtension.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Factory
{
	public static function createLorem(...$args): Lorem
	{
		return new Lorem(...$args);
	}
}


class IpsumFactory
{
	public static function create(...$args): Ipsum
	{
		return new Ipsum(...$args);
	}
}


class Lorem
{
	public function __construct(...$args)
	{
		Notes::add(__METHOD__ . ' ' . implode(' ', $args));
	}
}

class Ipsum
{
	public function __construct(...$args)
	{
		Notes::add(__METHOD__ . ' ' . implode(' ', $args));
	}
}


class FooExtension extends Nette\DI\CompilerExtension
{
	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition('one1')
			->setFactory(Lorem::class, [1]);
		$builder->addDefinition('one2')
			->setFactory(Lorem::class, [1]);
		$builder->addDefinition('one3')
			->setFactory(Lorem::class, [1]);
		$builder->addDefinition('one4')
			->setFactory(Lorem::class, [1]);
		$builder->addDefinition('one5')
			->setFactory(Lorem::class, [1]);
		$builder->addDefinition('one6')
			->setFactory(Lorem::class, [1]);
		$builder->addDefinition('one7')
			->setFactory(Lorem::class, [1]);
		$builder->addDefinition('one8')
			->setFactory(Lorem::class, [1])
			->addSetup('__construct', [2]);
		$builder->addDefinition('one9')
			->setFactory(Lorem::class, [1]);
		$builder->addDefinition('one10')
			->setFactory(Lorem::class, [1]);

		$builder->addDefinition('two1')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('two2')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('two3')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('two4')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1, 2]);
		$builder->addDefinition('two5')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('two6')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1, 2]);
		$builder->addDefinition('two7')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('two8')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1, 2]);
		$builder->addDefinition('two9')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1, 2]);
		$builder->addDefinition('two10')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('two11')
			->setType(Lorem::class)
			->setFactory('Factory::createLorem', [1]);

		$builder->addDefinition('three1')
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('three2')
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('three3')
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('three4')
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('three5')
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('three6')
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('three7')
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('three8')
			->setFactory('Factory::createLorem', [1]);
		$builder->addDefinition('three9')
			->setFactory('Factory::createLorem', [1]);
	}
}


$compiler = new DI\Compiler;
$extension = new FooExtension;
$compiler->addExtension('database', $extension);
$container = createContainer($compiler, 'files/compiler.extensionOverride.neon');


Assert::type(Ipsum::class, $container->getService('one1'));
Assert::same([
	'Ipsum::__construct ',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('one2'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('one3'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Lorem::class, $container->getService('one4'));
Assert::same([
	'Lorem::__construct 2',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('one5'));
Assert::same([
	'Ipsum::__construct ',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('one6'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('one7'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('one8'));
Assert::same([
	'Ipsum::__construct ',
], Notes::fetch());

Assert::exception(function () use ($container) {
	$container->getService('one9');
}, TypeError::class, '%a% must be %a% Ipsum,%a?% Lorem returned');
Notes::fetch();

Assert::type(Ipsum::class, $container->getService('one10'));
Assert::same([
	'Ipsum::__construct ',
], Notes::fetch());


Assert::type(Ipsum::class, $container->getService('two1'));
Assert::same([
	'Ipsum::__construct ',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('two2'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('two3'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Lorem::class, $container->getService('two4'));
Assert::same([
	'Lorem::__construct 2',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('two5'));
Assert::same([
	'Ipsum::__construct ',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('two6'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('two7'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Lorem::class, $container->getService('two8'));
Assert::same([
	'Lorem::__construct 1 new',
], Notes::fetch());

Assert::type(Lorem::class, $container->getService('two9'));
Assert::same([
	'Lorem::__construct new',
], Notes::fetch());

Assert::type(Lorem::class, $container->getService('two10'));
Assert::same([
	'Lorem::__construct 2 new',
], Notes::fetch());

Assert::exception(function () use ($container) {
	$container->getService('two11');
}, TypeError::class, '%a% must be %a% Ipsum,%a?% Lorem returned');
Notes::fetch();

Assert::type(Ipsum::class, $container->getService('two12'));
Assert::same([
	'Ipsum::__construct ',
], Notes::fetch());



Assert::type(Ipsum::class, $container->getService('three1'));
Assert::same([
	'Ipsum::__construct ',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('three2'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('three3'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Lorem::class, $container->getService('three4'));
Assert::same([
	'Lorem::__construct 2',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('three5'));
Assert::same([
	'Ipsum::__construct ',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('three6'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::type(Ipsum::class, $container->getService('three7'));
Assert::same([
	'Ipsum::__construct 2',
], Notes::fetch());

Assert::exception(function () use ($container) {
	$container->getService('three8');
}, TypeError::class, '%a% must be %a% Ipsum,%a?% Lorem returned');
Notes::fetch();

Assert::type(Ipsum::class, $container->getService('three9'));
Assert::same([
	'Ipsum::__construct ',
], Notes::fetch());
