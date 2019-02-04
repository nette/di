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
	protected $types = [
		'one' => 'One',
		'two' => 'Two',
	];

	protected $aliases = [
		'three' => 'one',
	];
}


$container = new MyContainer;

Assert::same('One', $container->getServiceType('one'));
Assert::same('Two', $container->getServiceType('two'));
Assert::same('One', $container->getServiceType('three'));

Assert::exception(function () use ($container) {
	$container->getServiceType('four');
}, Nette\DI\MissingServiceException::class, "Service 'four' not found.");
