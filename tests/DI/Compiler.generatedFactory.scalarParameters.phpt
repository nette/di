<?php

/**
 * Test: Nette\DI\Compiler: generated services factories from interfaces with scalar type hints in parameters.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


interface IArticleFactory
{

	/** @return Article */
	function create(string $title);
}

class Article
{
	public $title;

	function __construct(string $title)
	{
		$this->title = $title;
	}
}

$compiler = new DI\Compiler;
$container = createContainer($compiler, 'files/compiler.generatedFactory.scalarParameters.neon');

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
