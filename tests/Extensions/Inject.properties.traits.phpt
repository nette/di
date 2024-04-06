<?php declare(strict_types=1);

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
	use Nette\DI\Attributes\Inject;

	trait BTrait
	{
		#[Inject]
		public AInjected $varA;
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
