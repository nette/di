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
		'varA' => [
			'type' => A\AInjected::class,
			'tag' => null,
		],
		'varB' => [
			'type' => A\B\BInjected::class,
			'tag' => null,
		],
		'varC' => [
			'type' => A\AInjected::class,
			'tag' => null,
		],
	], InjectExtension::getInjectProperties(A\AClass::class));

	Assert::same([
		'varA' => [
			'type' => A\AInjected::class,
			'tag' => null,
		],
		'varB' => [
			'type' => A\B\BInjected::class,
			'tag' => null,
		],
		'varC' => [
			'type' => A\AInjected::class,
			'tag' => null,
		],
		'varF' => [
			'type' => A\B\BInjected::class,
			'tag' => null,
		],
	], InjectExtension::getInjectProperties(A\B\BClass::class));

	Assert::same([
		'var1' => [
			'type' => A\AInjected::class,
			'tag' => null,
		],
		'var2' => [
			'type' => A\B\BInjected::class,
			'tag' => null,
		],
		'var3' => [
			'type' => C\CInjected::class,
			'tag' => null,
		],
		'var4' => [
			'type' => C\CInjected::class,
			'tag' => null,
		],
	], InjectExtension::getInjectProperties(C\CClass::class));

	Assert::exception(
		fn() => InjectExtension::getInjectProperties(A\BadClass::class),
		Nette\InvalidStateException::class,
		"Type of property A\\BadClass::\$var is expected to not be nullable/built-in/complex, 'A\\AClass|stdClass' given.",
	);
}
