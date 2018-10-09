<?php

/**
 * Test: Nette\DI\Compiler and loadDefinitions.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$builder = new DI\ContainerBuilder;
$config = (new DI\Config\Adapters\NeonAdapter)->load(__DIR__ . '/files/compiler.parseServices.namespace.neon');
$config['services']['articlesList']['factory']->arguments[0] = new Reference('extension.articles');
DI\Compiler::loadDefinitions($builder, $config['services'], 'blog');


Assert::same('@blog.articles', $builder->getDefinition('blog.comments')->getFactory()->arguments[1]);
Assert::equal(new Reference('blog.articles'), $builder->getDefinition('blog.articlesList')->getFactory()->arguments[0]);
Assert::equal('@blog.comments', $builder->getDefinition('blog.commentsControl')->getFactory()->arguments[0]->getEntity());
