<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectProperties()
 */

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
		'varA' => 'A\AInjected',
		'varB' => 'A\B\BInjected',
		'varC' => 'A\AInjected',
	], InjectExtension::getInjectProperties('A\AClass'));

	Assert::same([
		'varA' => 'A\AInjected',
		'varB' => 'A\B\BInjected',
		'varC' => 'A\AInjected',
		'varF' => 'A\B\BInjected',
	], InjectExtension::getInjectProperties('A\B\BClass'));

	Assert::same([
		'var1' => 'A\AInjected',
		'var2' => 'A\B\BInjected',
		'var3' => 'C\CInjected',
		'var4' => 'C\CInjected',
	], InjectExtension::getInjectProperties('C\CClass'));
}
