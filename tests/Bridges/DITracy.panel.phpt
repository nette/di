<?php declare(strict_types=1);

/**
 * Test: DIExtension & Tracy integration
 */

use Nette\DI;
use Nette\DI\Extensions\DIExtension;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$compiler = new DI\Compiler;
$compiler->addExtension('tracy', new Tracy\Bridges\Nette\TracyExtension(debugMode: true));
$compiler->addExtension('di', new DIExtension(debugMode: true));
$container = createContainer($compiler, '
parameters:
	database: [user: admin, password: secret1]

services:
	-
		create: stdClass
		setup:
			- $password = secret2
');
$container->initialize();
$container->getByType(stdClass::class);

$bar = $container->getByType(Tracy\Bar::class);
$panel = $bar->getPanel(Nette\Bridges\DITracy\ContainerPanel::class);
Assert::type(Nette\Bridges\DITracy\ContainerPanel::class, $panel);


// sensitive keys are hidden in parameters and services
$html = $panel->getPanel();
Assert::contains('admin', $html);
Assert::notContains('secret1', $html);
Assert::notContains('secret2', $html);
