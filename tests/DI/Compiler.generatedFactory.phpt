<?php

/**
 * Test: Nette\DI\Compiler: generated services factories.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;
use Nette\DI\Config\Adapters as Adapt;


require __DIR__ . '/../bootstrap.php';


interface ILoremFactory
{

	/**
	 * @return Lorem
	 */
	function create();
}

class Lorem
{

	public $ipsum;

	function __construct(Ipsum $ipsum)
	{
		$this->ipsum = $ipsum;
	}

}

interface IFinderFactory
{
	/**
	 * @return Adapt\NeonAdapter comment
	 */
	function create();
}

interface IArticleFactory
{

	/**
	 * @param string
	 * @return Article
	 */
	function create($title);
}

class Article
{
	public $title;

	function __construct($title)
	{
		$this->title = $title;
	}
}

class Ipsum
{

}

class Foo
{
	public $bar;
	public $baz;

	public function __construct(Bar $bar, Baz $baz = NULL)
	{
		$this->bar = $bar;
		$this->baz = $baz;
	}
}

class Bar
{

}

class Baz
{

}

interface IFooFactory
{
	/**
	 * @param Baz
	 * @return Foo
	 */
	public function create(Baz $baz = NULL);
}

class Dolor
{
	public $bar;

	public $foo;

	public function __construct(Bar $bar = NULL, $foo)
	{
		$this->bar = $bar;
		$this->foo = $foo;
	}
}

interface DolorFactory
{

	/** @return Dolor */
	public function create(Bar $bar = NULL, $foo);

}


class TestClass
{
	public $foo;
	public $bar;
	public function __construct($foo, $bar)
	{
		$this->foo = $foo;
		$this->bar = $bar;
	}
}

interface ITestClassFactory
{
	/** @return TestClass */
	public function create($bar);
}

class TestExtension extends DI\CompilerExtension
{
	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition('fooFactory2')
			->setFactory('Foo')
			->setParameters(['Baz baz' => NULL])
			->setImplement('IFooFactory')
			->setArguments([1 => $builder::literal('$baz')]);

		// see definition by config in Compiler::parseService()
	}
}

$compiler = new DI\Compiler;
$compiler->addExtension('test', new TestExtension);
$container = createContainer($compiler, 'files/compiler.generatedFactory.neon');


Assert::type(ILoremFactory::class, $container->getService('lorem'));
$lorem = $container->getService('lorem')->create();
Assert::type(Lorem::class, $lorem);
Assert::type(Ipsum::class, $lorem->ipsum);
Assert::same($container->getService('ipsum'), $lorem->ipsum);

Assert::type(ILoremFactory::class, $container->getByType('ILoremFactory'));

Assert::type(IFinderFactory::class, $container->getService('finder'));
$finder = $container->getService('finder')->create();
Assert::type(Nette\DI\Config\Adapters\NeonAdapter::class, $finder);


Assert::type(IArticleFactory::class, $container->getService('article'));
$article = $container->getService('article')->create('nemam');
Assert::type(Article::class, $article);
Assert::same('nemam', $article->title);


Assert::type(IFooFactory::class, $container->getService('fooFactory1'));
$foo = $container->getService('fooFactory1')->create($container->getService('baz'));
Assert::type(Foo::class, $foo);
Assert::type(Bar::class, $foo->bar);
Assert::same($container->getService('bar'), $foo->bar);
Assert::type(Baz::class, $foo->baz);
Assert::same($container->getService('baz'), $foo->baz);
$foo = $container->getService('fooFactory1')->create();
Assert::type(Foo::class, $foo);
Assert::type(Bar::class, $foo->bar);
Assert::same($container->getService('bar'), $foo->bar);
Assert::null($foo->baz);


Assert::type(IFooFactory::class, $container->getService('fooFactory2'));
$foo = $container->getService('fooFactory2')->create($container->getService('baz'));
Assert::type(Foo::class, $foo);
Assert::type(Bar::class, $foo->bar);
Assert::same($container->getService('bar'), $foo->bar);
Assert::type(Baz::class, $foo->baz);
Assert::same($container->getService('baz'), $foo->baz);
$foo = $container->getService('fooFactory2')->create();
Assert::type(Foo::class, $foo);
Assert::type(Bar::class, $foo->bar);
Assert::same($container->getService('bar'), $foo->bar);
Assert::null($foo->baz);


Assert::type(IArticleFactory::class, $container->getService('article2'));
$article = $container->getService('article2')->create('nemam');
Assert::type(Article::class, $article);
Assert::same('nemam', $article->title);


Assert::type(IFooFactory::class, $container->getService('fooFactory3'));
$foo = $container->getService('fooFactory3')->create($container->getService('baz'));
Assert::type(Foo::class, $foo);
Assert::type(Bar::class, $foo->bar);
Assert::same($container->getService('bar'), $foo->bar);
Assert::type(Baz::class, $foo->baz);
Assert::same($container->getService('baz'), $foo->baz);
$foo = $container->getService('fooFactory3')->create();
Assert::type(Foo::class, $foo);
Assert::type(Bar::class, $foo->bar);
Assert::same($container->getService('bar'), $foo->bar);
Assert::null($foo->baz);


Assert::type(IFooFactory::class, $container->getService('fooFactory4'));
$foo = $container->getService('fooFactory4')->create($container->getService('baz'));
Assert::type(Foo::class, $foo);
Assert::type(Bar::class, $foo->bar);
Assert::same($container->getService('bar'), $foo->bar);
Assert::type(Baz::class, $foo->baz);
Assert::same($container->getService('baz'), $foo->baz);
$foo = $container->getService('fooFactory4')->create();
Assert::type(Foo::class, $foo);
Assert::type(Bar::class, $foo->bar);
Assert::same($container->getService('bar'), $foo->bar);
Assert::null($foo->baz);


Assert::type(ITestClassFactory::class, $container->getService('factory5'));
$obj = $container->getService('factory5')->create('bar');
Assert::same('foo', $obj->foo);
Assert::same('bar', $obj->bar);

Assert::type(DolorFactory::class, $factory = $container->getService('dolorFactory'));
Assert::type(Dolor::class, $obj = $factory->create($bar = new Bar(), 'abc'));
Assert::same($bar, $obj->bar);
Assert::same('abc', $obj->foo);

Assert::type(Dolor::class, $obj = $factory->create(NULL, 'abc'));
Assert::null($obj->bar);
Assert::same('abc', $obj->foo);


class Bad1
{
	public function __construct(Bar $bar)
	{
	}
}

interface Bad2
{
	public function create(Baz $bar);
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setImplement('Bad2')->setFactory('Bad1');
	$builder->complete();
}, Nette\InvalidStateException::class, 'Type hint for $bar in Bad2::create() doesn\'t match type hint in Bad1 constructor.');



class Bad3
{
	public function __construct($bar)
	{
	}
}

interface Bad4
{
	public function create($baz);
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setImplement('Bad4')->setFactory('Bad3');
	$builder->complete();
}, Nette\InvalidStateException::class, 'Unused parameter $baz when implementing method Bad4::create(), did you mean $bar?');



class Bad5
{
	public function __construct($xxx)
	{
	}
}

interface Bad6
{
	public function create($baz);
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setImplement('Bad6')->setFactory('Bad5');
	$builder->complete();
}, Nette\InvalidStateException::class, 'Unused parameter $baz when implementing method Bad6::create().');
