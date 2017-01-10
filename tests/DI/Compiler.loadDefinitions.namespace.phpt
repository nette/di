<?php

/**
 * Test: Nette\DI\Compiler and loadDefinitions.
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$builder = new DI\ContainerBuilder;
$config = (new DI\Config\Adapters\NeonAdapter())->load(__DIR__ . '/files/compiler.parseServices.namespace.neon');
DI\Compiler::loadDefinitions($builder, $config['services'], 'blog');


Assert::same('@blog.articles', $builder->getDefinition('blog.comments')->getFactory()->arguments[1]);
Assert::same('@blog.articles', $builder->getDefinition('blog.articlesList')->getFactory()->arguments[0]);
Assert::same('@blog.comments', $builder->getDefinition('blog.commentsControl')->getFactory()->arguments[0]->getEntity());
