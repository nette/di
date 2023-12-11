<?php

/**
 * Test: Nette\DI\Compiler: generated services factories from interfaces with nullable parameters.
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
	public function create(?string $title, ?Foo $foo, ?int $lorem = 1): Article;
}

class Article
{
	public $title;

	public $foo;

	public $lorem;


	public function __construct(?string $title, ?Foo $foo, ?int $lorem = null)
	{
		$this->title = $title;
		$this->foo = $foo;
		$this->lorem = $lorem;
	}
}

$compiler = new DI\Compiler;
// parameters are deprecated
$container = @createContainer($compiler, '
services:

	article:
		create: Article(%title%, %foo%, %lorem%)
		implement: IArticleFactory
		parameters: [?string title, ?Foo foo, ?int lorem: null]

	article2:
		implement: IArticleFactory
		arguments: [%title%, %foo%, %lorem%]
		parameters: [?string title, ?Foo foo, ?int lorem: null]

	article3:
		implement: IArticleFactory
');

foreach (['article', 'article2', 'article3'] as $serviceName) {
	$service = $container->getService($serviceName);
	Assert::type(IArticleFactory::class, $service);
	$article = $service->create('lorem-ipsum', $foo = new Foo, 1);
	Assert::type(Article::class, $article);
	Assert::same('lorem-ipsum', $article->title);
	Assert::same($foo, $article->foo);
	Assert::same(1, $article->lorem);

	$article = $service->create(null, null);
	Assert::type(Article::class, $article);
	Assert::null($article->title);
	Assert::null($article->foo);
	Assert::same($serviceName === 'article3' ? 1 : null, $article->lorem);
}
