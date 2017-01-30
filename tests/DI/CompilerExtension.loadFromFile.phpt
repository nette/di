<?php

/**
 * Test: Nette\DI\CompilerExtension::loadFromFile()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyExtension extends Nette\DI\CompilerExtension
{
}


test(function () {
	$ext = new MyExtension;
	$ext->setCompiler(new DI\Compiler, 'my');
	$res = $ext->loadFromFile(__DIR__ . '/files/compilerExtension.loadFromFile.neon');
	Assert::equal([
		'services' => [
			'one' => [
				'class' => 'Ipsum',
			],
		],
	], $res);
});
