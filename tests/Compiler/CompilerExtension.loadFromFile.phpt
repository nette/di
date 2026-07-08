<?php declare(strict_types=1);

/**
 * Test: Nette\DI\CompilerExtension::loadFromFile()
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyExtension extends Nette\DI\CompilerExtension
{
}


$config = '
services:
	one:
		create: Ipsum
';
$ext = new MyExtension;
$ext->setCompiler(new DI\Compiler, 'my');
$res = $ext->loadFromFile(Tester\FileMock::create($config, 'neon'));
Assert::equal([
	'services' => [
		'one' => [
			'create' => 'Ipsum',
		],
	],
], $res);
