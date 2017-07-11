<?php

/**
 * Test: Nette\DI\Helpers::getReturnType
 * @phpversion 7
 */

declare(strict_types=1);

namespace NS
{
	use Test\B;

	class A
	{
		public function noType()
		{
		}


		public function classType(): B
		{
		}


		public function nativeType(): string
		{
		}


		public function selfType(): self
		{
		}


		/** @return B */
		public function annotationClassType()
		{
		}


		/** @return B|string */
		public function annotationUnionType()
		{
		}


		/** @return String */
		public function annotationNativeType()
		{
		}


		/** @return self */
		public function annotationSelfType()
		{
		}


		/** @return static */
		public function annotationStaticType()
		{
		}
	}


	/** @return B */
	function annotationClassType()
	{
	}
}

namespace
{
	use Nette\DI\Helpers;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	Assert::null(Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'noType')));

	Assert::same('Test\B', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'classType')));

	Assert::same('string', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'nativeType')));

	Assert::same('NS\A', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'selfType')));

	Assert::same('Test\B', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationClassType')));

	Assert::same('Test\B', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationUnionType')));

	Assert::same('string', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationNativeType')));

	Assert::same('NS\A', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationSelfType')));

	Assert::same('NS\A', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationStaticType')));

	// class name expanding is NOT supported for global functions
	Assert::same('B', Helpers::getReturnType(new \ReflectionFunction('NS\annotationClassType')));
}
