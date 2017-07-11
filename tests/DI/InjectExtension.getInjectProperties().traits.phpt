<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectProperties() with traits
 */

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
	class CClass
	{
		use \B\BTrait;
	}
}

namespace
{
	use Nette\DI\Extensions\InjectExtension;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	Assert::same([
		'varA' => 'A\AInjected',
	], InjectExtension::getInjectProperties('C\CClass'));
}
