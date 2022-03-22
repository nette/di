<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectProperties()
 * @phpVersion 7.4
 */

declare(strict_types=1);

namespace A
{
	class AClass
	{
		/** @var Different @inject */
		public AInjected $varA;

		/** @inject */
		public AInjected $varC;

		public AInjected $varD;
	}

	class AInjected
	{
	}
}

namespace {
	use Nette\DI\Extensions\InjectExtension;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	Assert::same([
		'varA' => A\AInjected::class,
		'varC' => A\AInjected::class,
	], InjectExtension::getInjectProperties(A\AClass::class));
}
