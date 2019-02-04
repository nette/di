<?php

/**
 * Test: Nette\DI\Container dynamic usage.
 */

declare(strict_types=1);

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class Service
{
	public static function create()
	{
		return new static;
	}
}


$container = new Container;

test(function () use ($container) {
	$one = new Service;
	$two = new Service;
	@$container->addService('one', $one); // @ triggers service should be defined as "imported"
	@$container->addService('two', $two); // @ triggers service should be defined as "imported"

	Assert::true($container->hasService('one'));
	Assert::true($container->isCreated('one'));
	Assert::true($container->hasService('two'));
	Assert::false($container->hasService('undefined'));

	Assert::same($one, $container->getService('one'));
	Assert::same($two, $container->getService('two'));
});
