<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectProperties() with traits
 * @phpversion 5.4
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


	Assert::same( array(
		'varA' => 'A\AInjected',
	), InjectExtension::getInjectProperties('C\CClass') );
}
