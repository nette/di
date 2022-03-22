<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectProperties() with traits
 */

declare(strict_types=1);

namespace A
{
	class AInjected
	{
	}
}

namespace B
{
	use A\AInjected;

	trait BTrait
	{
		/** @var AInjected @inject */
		public $varA;
	}
}

namespace C
{
	use B;

	class CClass
	{
		use B\BTrait;
	}
}

namespace {
	use Nette\DI\Extensions\InjectExtension;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	Assert::same([
		'varA' => A\AInjected::class,
	], InjectExtension::getInjectProperties(C\CClass::class));
}
