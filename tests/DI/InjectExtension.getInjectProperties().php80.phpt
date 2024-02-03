<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectProperties()
 * @phpVersion 8.0
 */

declare(strict_types=1);

use Nette\DI\Attributes\Inject;
use Nette\DI\Extensions\InjectExtension;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class AClass
{
	/** @inject */
	public AClass|stdClass $var;
}

class EClass
{
	#[Inject]
	public stdClass $varA;
}


Assert::exception(
	fn() => InjectExtension::getInjectProperties(AClass::class),
	Nette\InvalidStateException::class,
	"Type of property AClass::\$var is expected to not be nullable/built-in/complex, 'AClass|stdClass' given.",
);

Assert::same([
	'varA' => 'stdClass',
], InjectExtension::getInjectProperties(EClass::class));
