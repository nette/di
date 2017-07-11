<?php

/**
 * Test: Nette\DI\Container getServiceType.
 */

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyContainer extends Container
{
	protected $meta = [
		'services' => [
			'one' => 'One',
			'two' => 'Two',
		],
		'aliases' => [
			'three' => 'one',
		],
	];
}


$container = new MyContainer;

Assert::same('One', $container->getServiceType('one'));
Assert::same('Two', $container->getServiceType('two'));
Assert::same('One', $container->getServiceType('three'));

Assert::exception(function () use ($container) {
	$container->getServiceType('four');
}, Nette\DI\MissingServiceException::class, "Service 'four' not found.");
