<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectProperties()
 * @phpVersion 8.0
 */

declare(strict_types=1);

use Nette\DI\Extensions\InjectExtension;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class AClass
{
	/** @inject */
	public AClass|\stdClass $var;
}


Assert::exception(function () {
	InjectExtension::getInjectProperties(AClass::class);
}, Nette\InvalidStateException::class, 'The AClass::$var is not expected to have a union type.');
