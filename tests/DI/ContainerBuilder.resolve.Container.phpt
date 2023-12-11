<?php

/**
 * Test: Nette\DI\Container::getByType() can be resolved
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Lorem
{
	public function next(): stdClass
	{
		return new stdClass;
	}
}


$container = createContainer(new DI\Compiler, '
services:
	lorem: Lorem

	next:
		create: @container::getByType(Lorem)::next()
		type: stdClass
');


Assert::type(stdClass::class, $container->getService('next'));
