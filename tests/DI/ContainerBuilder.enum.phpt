<?php

/**
 * @phpVersion 8.1
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


enum Suit
{
	case Clubs;
	case Diamonds;
	case Hearts;
	case Spades;
}


class Foo
{
	public function __construct(Suit $suit)
	{
	}
}


$compiler = new DI\Compiler;
$container = createContainer($compiler, '
services:
	foo: Foo(Suit::Clubs)
');

Assert::type(Foo::class, $container->getService('foo'));
