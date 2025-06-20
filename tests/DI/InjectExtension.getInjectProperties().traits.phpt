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
	use Nette\DI\Attributes\Inject;

	trait BTrait
	{
		#[Inject]
		public AInjected $varA;

		#[Inject(tag: 'tagB')]
		public AInjected $varB;
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
		'varA' => [
			'type' => A\AInjected::class,
			'tag' => null,
		],
		'varB' => [
			'type' => A\AInjected::class,
			'tag' => 'tagB',
		],
	], InjectExtension::getInjectProperties(C\CClass::class));
}
