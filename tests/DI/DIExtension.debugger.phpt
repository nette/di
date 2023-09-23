<?php

/**
 * Test: DIExtension & Tracy integration
 */

declare(strict_types=1);

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$compiler = new DI\Compiler;
$compiler->addExtension('tracy', new Tracy\Bridges\Nette\TracyExtension(true));
$compiler->addExtension('di', new DIExtension(true));
$container = createContainer($compiler, '');
$container->initialize();

$bar = $container->getByType(Tracy\Bar::class);
$panel = $bar->getPanel(Nette\Bridges\DITracy\ContainerPanel::class);
Assert::type(Nette\Bridges\DITracy\ContainerPanel::class, $panel);
