<?php

/**
 * Test: Nette\DI\CompilerExtension and loadDefinitionsFromConfig.
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Definitions\Reference;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class CompilerExtension extends DI\CompilerExtension
{
}


$config = (new DI\Config\Adapters\NeonAdapter)->load(__DIR__ . '/files/compiler.parseServices.namespace.neon');
$config['services']['articlesList']['factory']->arguments[0] = new Reference('extension.articles');

$builder = new DI\ContainerBuilder;
$compiler = new DI\Compiler($builder);
$compilerExtension = (new CompilerExtension)->setCompiler($compiler, 'blog');
$compilerExtension->loadDefinitionsFromConfig($config['services']);


Assert::same('@blog.articles', $builder->getServiceDefinition('blog.comments')->getFactory()->arguments[1]);
Assert::equal(new Reference('blog.articles'), $builder->getServiceDefinition('blog.articlesList')->getFactory()->arguments[0]);
Assert::equal(new Reference('blog.comments'), $builder->getServiceDefinition('blog.commentsControl')->getFactory()->arguments[0]->getEntity());
