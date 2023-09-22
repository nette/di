<?php

/**
 * Test: Nette\DI\Container getServiceType.
 */

declare(strict_types=1);

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyContainer extends Container
{
	protected array $aliases = [
		'three' => 'one',
	];


	public function createServiceOne(): One
	{
	}


	public function createServiceTwo(): Two
	{
	}
}


$container = new MyContainer;

Assert::same('One', $container->getServiceType('one'));
Assert::same('Two', $container->getServiceType('two'));
Assert::same('One', $container->getServiceType('three'));

Assert::exception(
	fn() => $container->getServiceType('four'),
	Nette\DI\MissingServiceException::class,
	"Service 'four' not found.",
);
