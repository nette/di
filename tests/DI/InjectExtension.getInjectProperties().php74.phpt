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

		/** @inject */
		protected AInjected $varE;
	}

	class AInjected
	{
	}
}

namespace
{
	use Nette\DI\Extensions\InjectExtension;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	Assert::same([
		'varA' => 'A\AInjected',
		'varC' => 'A\AInjected',
	], InjectExtension::getInjectProperties('A\AClass'));
}
