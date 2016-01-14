<?php

/**
 * Test: Nette\DI\Container getServiceType.
 */

use Nette\DI\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class MyContainer extends Container
{

	protected $meta = array(
		'services' => array(
			'one' => 'One',
			'two' => 'Two',
		),
		'aliases' => array(
			'three' => 'one',
		),
	);

}


$container = new MyContainer;

Assert::same('One', $container->getServiceType('one'));
Assert::same('Two', $container->getServiceType('two'));
Assert::same('One', $container->getServiceType('three'));

Assert::exception(function () use ($container) {
	$container->getServiceType('four');
}, 'Nette\DI\MissingServiceException', "Service 'four' not found.");
