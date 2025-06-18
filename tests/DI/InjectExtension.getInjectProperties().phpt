<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectProperties()
 */

declare(strict_types=1);

namespace A
{
	class AClass
	{
		/** @var Different @inject */
		public AInjected $varA;

		/** @var B\BInjected @inject */
		public $varB;

		/** @inject */
		public AInjected $varC;

		/** @var AInjected */
		public $varD;
	}

	class AInjected
	{
	}

	class BadClass
	{
		/** @inject */
		public AClass|\stdClass $var;
	}
}

namespace A\B
{
	use A;
	use Nette\DI\Attributes\Inject;

	class BClass extends A\AClass
	{
		#[Inject]
		public BInjected $varF;
	}

	class BInjected
	{
	}
}

namespace C
{
	use A\AInjected;
	use A\B;
	use C\CInjected as CAlias;

	class CClass
	{
		/** @var AInjected @inject */
		public $var1;

		/** @var B\BInjected @inject */
		public $var2;

		/** @var CAlias @inject */
		public $var3;

		/** @var CInjected @inject */
		public $var4;
	}

	class CInjected
	{
	}
}

namespace {
	use Nette\DI\Extensions\InjectExtension;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	Assert::same([
		'varA' => A\AInjected::class,
		'varB' => A\B\BInjected::class,
		'varC' => A\AInjected::class,
	], InjectExtension::getInjectProperties(A\AClass::class));

	Assert::same([
		'varA' => A\AInjected::class,
		'varB' => A\B\BInjected::class,
		'varC' => A\AInjected::class,
		'varF' => A\B\BInjected::class,
	], InjectExtension::getInjectProperties(A\B\BClass::class));

	Assert::same([
		'var1' => A\AInjected::class,
		'var2' => A\B\BInjected::class,
		'var3' => C\CInjected::class,
		'var4' => C\CInjected::class,
	], InjectExtension::getInjectProperties(C\CClass::class));

	Assert::exception(
		fn() => InjectExtension::getInjectProperties(A\BadClass::class),
		Nette\InvalidStateException::class,
		"Type of property A\\BadClass::\$var is expected to not be nullable/built-in/complex, 'A\\AClass|stdClass' given.",
	);
}
