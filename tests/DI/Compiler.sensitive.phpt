<?php

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Service
{
	public function __construct(
		$a,
		#[SensitiveParameter]
		$sen,
	) {
	}


	public function foo(
		#[SensitiveParameter]
		$sen,
		$b,
	) {
	}
}


$config = '
services:
	- create: Service(a, b, c)
	  setup:
	  	- foo(a, b, c)

';
$loader = new DI\Config\Loader;
$compiler = new DI\Compiler;
$compiler->addConfig($loader->load(Tester\FileMock::create($config, 'neon')));
$code = $compiler->compile();

Assert::contains("new Service('a', /*sensitive{*/'b'/*}*/, 'c')", $code);
Assert::contains("foo(/*sensitive{*/'a'/*}*/, 'b', 'c')", $code);
