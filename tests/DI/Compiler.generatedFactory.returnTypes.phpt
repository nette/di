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

	function create($title): Article;
}

class Article
{
	public $title;

	function __construct($title)
	{
		$this->title = $title;
	}
}

$compiler = new DI\Compiler;
$container = createContainer($compiler, 'files/compiler.generatedFactory.returnTypes.neon');

Assert::type(IArticleFactory::class, $container->getService('article'));
$article = $container->getService('article')->create('lorem-ipsum');
Assert::type(Article::class, $article);
Assert::same('lorem-ipsum', $article->title);

Assert::type(IArticleFactory::class, $container->getService('article2'));
$article = $container->getService('article2')->create('lorem-ipsum');
Assert::type(Article::class, $article);
Assert::same('lorem-ipsum', $article->title);
