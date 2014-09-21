<?php

/**
 * Test: Nette\DI\Compiler: generated services factories.
 */

use Nette\DI;
use Tester\Assert;
use Nette\Utils as NU;


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
	 * @return NU\Finder comment
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

class TestExtension extends DI\CompilerExtension
{
	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition('fooFactory2')
			->setFactory('Foo')
			->setParameters(array('Baz baz' => NULL))
			->setImplement('IFooFactory')
			->setArguments(array(1 => $builder::literal('$baz')));

		// see definition by config in Compiler::parseService()
	}
}

$compiler = new DI\Compiler;
$compiler->addExtension('test', new TestExtension);
$container = createContainer($compiler, 'files/compiler.generatedFactory.neon');


Assert::type( 'ILoremFactory', $container->getService('lorem') );
$lorem = $container->getService('lorem')->create();
Assert::type( 'Lorem', $lorem );
Assert::type( 'Ipsum', $lorem->ipsum );
Assert::same( $container->getService('ipsum'), $lorem->ipsum );

Assert::type( 'ILoremFactory', $container->getByType('ILoremFactory') );

Assert::type( 'IFinderFactory', $container->getService('finder') );
$finder = $container->getService('finder')->create();
Assert::type( 'Nette\Utils\Finder', $finder );


Assert::type( 'IArticleFactory', $container->getService('article') );
$article = $container->getService('article')->create('nemam');
Assert::type( 'Article', $article );
Assert::same( 'nemam', $article->title );


Assert::type( 'IFooFactory', $container->getService('fooFactory1') );
$foo = $container->getService('fooFactory1')->create($container->getService('baz'));
Assert::type( 'Foo', $foo );
Assert::type( 'Bar', $foo->bar );
Assert::same($container->getService('bar'), $foo->bar);
Assert::type( 'Baz', $foo->baz );
Assert::same($container->getService('baz'), $foo->baz);
$foo = $container->getService('fooFactory1')->create();
Assert::type( 'Foo', $foo );
Assert::type( 'Bar', $foo->bar );
Assert::same($container->getService('bar'), $foo->bar);
Assert::null( $foo->baz );


Assert::type( 'IFooFactory', $container->getService('fooFactory2') );
$foo = $container->getService('fooFactory2')->create($container->getService('baz'));
Assert::type( 'Foo', $foo );
Assert::type( 'Bar', $foo->bar );
Assert::same($container->getService('bar'), $foo->bar);
Assert::type( 'Baz', $foo->baz );
Assert::same($container->getService('baz'), $foo->baz);
$foo = $container->getService('fooFactory2')->create();
Assert::type( 'Foo', $foo );
Assert::type( 'Bar', $foo->bar );
Assert::same($container->getService('bar'), $foo->bar);
Assert::null( $foo->baz );


Assert::type('IArticleFactory', $container->getService('article2'));
$article = $container->getService('article2')->create('nemam');
Assert::type('Article', $article);
Assert::same('nemam', $article->title);


Assert::type( 'IFooFactory', $container->getService('fooFactory3') );
$foo = $container->getService('fooFactory3')->create($container->getService('baz'));
Assert::type( 'Foo', $foo );
Assert::type( 'Bar', $foo->bar );
Assert::same($container->getService('bar'), $foo->bar);
Assert::type( 'Baz', $foo->baz );
Assert::same($container->getService('baz'), $foo->baz);
$foo = $container->getService('fooFactory3')->create();
Assert::type( 'Foo', $foo );
Assert::type( 'Bar', $foo->bar );
Assert::same($container->getService('bar'), $foo->bar);
Assert::null( $foo->baz );


Assert::type( 'IFooFactory', $container->getService('fooFactory4') );
$foo = $container->getService('fooFactory4')->create($container->getService('baz'));
Assert::type( 'Foo', $foo );
Assert::type( 'Bar', $foo->bar );
Assert::same($container->getService('bar'), $foo->bar);
Assert::type( 'Baz', $foo->baz );
Assert::same($container->getService('baz'), $foo->baz);
$foo = $container->getService('fooFactory4')->create();
Assert::type( 'Foo', $foo );
Assert::type( 'Bar', $foo->bar );
Assert::same($container->getService('bar'), $foo->bar);
Assert::null( $foo->baz );


Assert::type( 'IFooFactory', $container->getService('fooFactory5') );
$foo = $container->getService('fooFactory5')->create($container->getService('baz'));
Assert::type( 'Foo', $foo );
Assert::type( 'Bar', $foo->bar );
Assert::same($container->getService('bar'), $foo->bar);
Assert::type( 'Baz', $foo->baz );
Assert::same($container->getService('baz'), $foo->baz);
$foo = $container->getService('fooFactory5')->create();
Assert::type( 'Foo', $foo );
Assert::type( 'Bar', $foo->bar );
Assert::same($container->getService('bar'), $foo->bar);
Assert::null( $foo->baz );


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

Assert::exception(function() {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition('one')->setImplement('Bad2')->setFactory('Bad1');
	$builder->generateClasses();
}, 'Nette\InvalidStateException', "Type hint for \$bar in Bad1::__construct() doesn't match type hint for \$bar in Bad2::create()");


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

Assert::type( 'ITestClassFactory', $container->getService('test1') );
$obj = $container->getService('test1')->create('bar');
Assert::same('foo', $obj->foo);
Assert::same('bar', $obj->bar);
