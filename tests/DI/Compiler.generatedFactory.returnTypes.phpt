<?php

/**
 * Test: Nette\DI\Compiler: generated services factories from interfaces with return type declarations.
 * @phpVersion 7.0
 */

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

Assert::type('IArticleFactory', $container->getService('article'));
$article = $container->getService('article')->create('lorem-ipsum');
Assert::type('Article', $article);
Assert::same('lorem-ipsum', $article->title);

Assert::type('IArticleFactory', $container->getService('article2'));
$article = $container->getService('article2')->create('lorem-ipsum');
Assert::type('Article', $article);
Assert::same('lorem-ipsum', $article->title);
