<?php

/**
 * Test: Nette\DI\Compiler: generated services factories from interfaces with return type declarations.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IArticleFactory
{
	public function create($title): Article;
}

class Article
{
	public $title;


	public function __construct($title)
	{
		$this->title = $title;
	}
}

class FooArticle extends Article
{
}

$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	article:
		create: Article
		implement: IArticleFactory

	article2:
		implement: IArticleFactory

	article3:
		implement: IArticleFactory
		create: FooArticle
');

Assert::type(IArticleFactory::class, $container->getService('article'));
$article = $container->getService('article')->create('lorem-ipsum');
Assert::type(Article::class, $article);
Assert::same('lorem-ipsum', $article->title);

Assert::type(IArticleFactory::class, $container->getService('article2'));
$article = $container->getService('article2')->create('lorem-ipsum');
Assert::type(Article::class, $article);
Assert::same('lorem-ipsum', $article->title);


Assert::type(IArticleFactory::class, $container->getService('article3'));
$article = $container->getService('article3')->create('lorem-ipsum');
Assert::type(FooArticle::class, $article);
Assert::same('lorem-ipsum', $article->title);
