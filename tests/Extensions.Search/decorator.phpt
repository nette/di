<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Extensions\SearchExtension registers services before DecoratorExtension decorates them.
 */

use Tester\Assert;

require __DIR__ . '/bootstrap.php';


$compiler = new Nette\DI\Compiler;
$compiler->addExtension('decorator', new Nette\DI\Extensions\DecoratorExtension);
$compiler->addExtension('search', new Nette\DI\Extensions\SearchExtension(getTempDir()));
createContainer($compiler, '
search:
	in: fixtures
	files: Ok1.php

decorator:
	ClassOk1:
		tags: [decorated]
');

$builder = $compiler->getContainerBuilder();
Assert::same(
	['decorated' => true],
	$builder->getDefinition($builder->getByType(ClassOk1::class))->getTags(),
);
