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

class EClass
{
	#[\Nette\DI\Attributes\Inject]
	public stdClass $varA;
}


Assert::exception(function () {
	InjectExtension::getInjectProperties(AClass::class);
}, Nette\InvalidStateException::class, "Type of property AClass::\$var is not expected to be union/intersection/built-in, 'AClass|stdClass' given.");

Assert::same([
	'varA' => 'stdClass',
], InjectExtension::getInjectProperties(EClass::class));
