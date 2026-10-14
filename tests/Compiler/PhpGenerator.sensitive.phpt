<?php declare(strict_types=1);

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


	public function bar(
		$a = null,
		#[SensitiveParameter]
		$sen = null,
	) {
	}
}


$config = '
services:
	foo: stdClass
	- create: Service(a, b, c)
	  setup:
	  	- foo(a, b, c)
	  	- foo(b: x, sen: y)
	  	- bar(sen: z)
	- Service(a, [user: u, password: p])
	- Service(a, [@foo, Service(x, y)])
';
$loader = new DI\Config\Loader;
$compiler = new DI\Compiler;
$compiler->addConfig($loader->load(Tester\FileMock::create($config, 'neon')));
$code = $compiler->compile();

Assert::contains("new Service('a', /*sensitive{*/'b'/*}*/, 'c')", $code);
Assert::contains("foo(/*sensitive{*/'a'/*}*/, 'b', 'c')", $code);
Assert::contains("foo(/*sensitive{*/'y'/*}*/, 'x')", $code);
Assert::contains("bar(sen: /*sensitive{*/'z'/*}*/)", $code);
Assert::contains("new Service('a', /*sensitive{*/['user' => 'u', 'password' => 'p']/*}*/)", $code);
Assert::contains("new Service('a', /*sensitive{*/[\$this->getService('foo'), new Service('x', /*sensitive{*/'y'/*}*/)]/*}*/)", $code);
