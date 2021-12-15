<?php

/**
 * Test: Nette\DI\Compiler: generated services factories.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Config\Adapters as Adapt;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface ILoremFactory
{
	public function create(): Lorem;
}

class Lorem
{
	public $ipsum;
	public $var;


	public function __construct(Ipsum $ipsum)
	{
		$this->ipsum = $ipsum;
	}
}

interface IFinderFactory
{
	/**
	 * @return Adapt\NeonAdapter comment
	 */
	public function create();
}

interface IArticleFactory
{
	public function create($title): Article;
}

class Article
{
	public const ABC = 123;

	public $title;
	public $method;
	public $prop;


	public function __construct($title)
	{
		$this->title = $title;
	}


	public function method($arg)
	{
		$this->method = $arg;
	}
}

class Ipsum
{
}

class Foo
{
	public $bar;

	public $baz;


	public function __construct(Bar $bar, ?Baz $baz = null)
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
	public function create(?Baz $baz = null): Foo;
}

class Dolor
{
	public $bar;

	public $foo;


	public function __construct(?Bar $bar, $foo)
	{
		$this->bar = $bar;
		$this->foo = $foo;
	}
}

interface DolorFactory
{
	public function create(?Bar $bar, $foo): Dolor;
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
	public function create($bar): TestClass;
}

class TestExtension extends DI\CompilerExtension
{
	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$builder->addFactoryDefinition('fooFactory2')
			->setParameters(['Baz baz' => null])
			->setImplement(IFooFactory::class)
			->getResultDefinition()
				->setCreator(Foo::class)
				->setArguments([1 => $builder::literal('$baz')]);

		$builder->addFactoryDefinition('overridenFactory')
			->setImplement(ILoremFactory::class)
			->setAutowired(false);

		// see definition by config in Compiler::parseService()
	}
}

$compiler = new DI\Compiler;
$compiler->addExtension('test', new TestExtension);
@$container = createContainer($compiler, 'files/compiler.generatedFactory.neon'); // missing type triggers warning


Assert::type(ILoremFactory::class, $container->getService('lorem'));
$lorem = $container->getService('lorem')->create();
Assert::type(Lorem::class, $lorem);
Assert::type(Ipsum::class, $lorem->ipsum);
Assert::same($container->getService('ipsum'), $lorem->ipsum);

Assert::type(ILoremFactory::class, $container->getByType(ILoremFactory::class));

Assert::type(IFinderFactory::class, $container->getService('finder'));
$finder = $container->getService('finder')->create();
Assert::type(Nette\DI\Config\Adapters\NeonAdapter::class, $finder);


Assert::type(IArticleFactory::class, $container->getService('article'));
$article = $container->getService('article')->create('nemam');
Assert::type(Article::class, $article);
Assert::same('nemam', $article->title);
Assert::same(123, $article->method);
Assert::same(123, $article->prop);


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


Assert::type(ILoremFactory::class, $container->getService('overridenFactory'));
$foo = $container->getService('overridenFactory')->create();
Assert::type(Lorem::class, $foo);
Assert::same(123, $foo->var);


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
Assert::type(Dolor::class, $obj = $factory->create($bar = new Bar, 'abc'));
Assert::same($bar, $obj->bar);
Assert::same('abc', $obj->foo);

Assert::type(Dolor::class, $obj = $factory->create(null, 'abc'));
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
	public function create(Baz $bar): Bad1;
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addFactoryDefinition('one')
		->setImplement(Bad2::class);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Bad2): Type of \$bar in Bad2::create() doesn't match type in Bad1 constructor.");



class Bad3
{
	public function __construct($bar)
	{
	}
}

interface Bad4
{
	public function create($baz): Bad3;
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addFactoryDefinition('one')
		->setImplement(Bad4::class);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Bad4): Unused parameter \$baz when implementing method Bad4::create(), did you mean \$bar?");



class Bad5
{
	public function __construct($xxx)
	{
	}
}

interface Bad6
{
	public function create($baz): Bad5;
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addFactoryDefinition('one')
		->setImplement(Bad6::class);
	$builder->complete();
}, Nette\InvalidStateException::class, "Service 'one' (type of Bad6): Unused parameter \$baz when implementing method Bad6::create().");



interface Bad7
{
	public function get(): stdClass;
}

Assert::exception(function () {
	$builder = new DI\ContainerBuilder;
	$builder->addDefinition(stdClass::class)->setCreator(stdClass::class);
	$builder->addAccessorDefinition('one')
		->setImplement(Bad7::class)
		->setClass(stdClass::class)
		->addSetup('method');
	$builder->complete();
}, Nette\MemberAccessException::class, 'Call to undefined method Nette\DI\Definitions\AccessorDefinition::addSetup().');
