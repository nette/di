<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectProperties()
 */

declare(strict_types=1);

use Nette\DI\Attributes\Inject;
use Nette\DI\Extensions\InjectExtension;
use Tester\Assert;


class AClass
{
	/** @inject */
	public AInjected $varA;

	/** @inject */
	public BInjected $varB;

	public $varD;

	#[Inject]
	public stdClass $varF;
}

class BadClass
{
	/** @inject */
	public AClass|stdClass $var;
}

class AInjected
{
}

class BInjected
{
}


require __DIR__ . '/../bootstrap.php';


Assert::same([
	'varA' => AInjected::class,
	'varB' => BInjected::class,
	'varF' => stdClass::class,
], InjectExtension::getInjectProperties(AClass::class));

Assert::exception(function () {
	InjectExtension::getInjectProperties(BadClass::class);
}, Nette\InvalidStateException::class, "Type of property BadClass::\$var is expected to not be nullable/built-in/complex, 'AClass|stdClass' given.");
