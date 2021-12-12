<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';


$loader = new Nette\Loaders\RobotLoader;
$loader->setTempDirectory(getTempDir());
$loader->addDirectory('fixtures');
$loader->reportParseErrors(false);
$loader->register();


function check(string $config): array
{
	$compiler = new Nette\DI\Compiler;
	$compiler->addExtension('search', new Nette\DI\Extensions\SearchExtension(getTempDir()));
	createContainer($compiler, $config);
	$res = [];
	foreach ($compiler->getContainerBuilder()->getDefinitions() as $def) {
		if ($def->getType() !== Nette\DI\Container::class) {
			$res[$def->getType()] = $def->getTags();
		}
	}

	ksort($res);
	return $res;
}
