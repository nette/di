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


$config = '
services:
	one:
		factory: Ipsum
';
$ext = new MyExtension;
$ext->setCompiler(new DI\Compiler, 'my');
$res = $ext->loadFromFile(Tester\FileMock::create($config, 'neon'));
Assert::equal([
	'services' => [
		'one' => [
			'factory' => 'Ipsum',
		],
	],
], $res);
