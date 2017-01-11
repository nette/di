<?php

/**
 * Test: Nette\DI\Compiler: generated services factories from interfaces with nullable parameters.
 * @phpVersion 7.1
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Foo
{

}

interface IArticleFactory
{

	function create(?string $title, ?Foo $foo, ?int $lorem = 1): Article;
}

class Article
{
	public $title;

	public $foo;

	public $lorem;


	function __construct(?string $title, ?Foo $foo, ?int $lorem = NULL)
	{
		$this->title = $title;
		$this->foo = $foo;
		$this->lorem = $lorem;
	}
}

$compiler = new DI\Compiler;
$container = createContainer($compiler, 'files/compiler.generatedFactory.nullableParameters.neon');

foreach (['article', 'article2', 'article3'] as $serviceName) {
	$service = $container->getService($serviceName);
	Assert::type(IArticleFactory::class, $service);
	$article = $service->create('lorem-ipsum', $foo = new Foo(), 1);
	Assert::type(Article::class, $article);
	Assert::same('lorem-ipsum', $article->title);
	Assert::same($foo, $article->foo);
	Assert::same(1, $article->lorem);

	$article = $service->create(NULL, NULL);
	Assert::type(Article::class, $article);
	Assert::null($article->title);
	Assert::null($article->foo);
	Assert::same($serviceName === 'article3' ? 1 : NULL, $article->lorem);
}
