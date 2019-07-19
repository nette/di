<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$compiler = new DI\Compiler;
$compiler->addExtension('extensions', new Nette\DI\Extensions\ExtensionsExtension);

Assert::exception(function () use ($compiler) {
	createContainer($compiler, '
	extensions:
		foo: stdClass
	');
}, Nette\DI\InvalidConfigurationException::class, "Extension should be Nette\\DI\\CompilerExtension, 'stdClass' given.");
