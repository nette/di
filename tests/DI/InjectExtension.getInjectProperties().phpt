<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectProperties()
 */

declare(strict_types=1);

namespace A
{
	class AClass
	{
		/** @var AInjected @inject */
		public $varA;

		/** @var B\BInjected @inject */
		public $varB;

		/** @var \A\AInjected @inject */
		public $varC;

		/** @var AInjected */
		public $varD;

		/** @var AInjected @inject */
		protected $varE;
	}

	class AInjected
	{
	}
}

namespace A\B
{
	class BClass extends \A\AClass
	{
		/** @var BInjected @inject */
		public $varF;
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

namespace
{
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
}
