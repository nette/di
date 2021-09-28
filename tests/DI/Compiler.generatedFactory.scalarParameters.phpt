<?php

/**
 * Test: Nette\DI\Compiler: generated services factories from interfaces with scalar type in parameters.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IArticleFactory
{
	public function create(string $title): Article;
}

class Article
{
	public $title;


	public function __construct(string $title)
	{
		$this->title = $title;
	}
}

$compiler = new DI\Compiler;
// parameters are deprecated
$container = @createContainer($compiler, '
services:
	article:
		factory: Article(%title%)
		implement: IArticleFactory
		parameters: [string title]

	article2:
		implement: IArticleFactory
		arguments: [%title%]
		parameters: [string title]

	article3:
		implement: IArticleFactory
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
Assert::type(Article::class, $article);
Assert::same('lorem-ipsum', $article->title);
