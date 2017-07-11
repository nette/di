<?php

/**
 * Test: Nette\DI\Helpers::getReturnType
 * @phpversion 7.1
 */

declare(strict_types=1);

namespace NS
{
	use Test\B;

	class A
	{
		function noType()
		{
		}


		function classType(): B
		{
		}


		function nativeType(): string
		{
		}


		function selfType(): self
		{
		}


		function nullableClassType(): ?B
		{
		}


		function nullableNativeType(): ?string
		{
		}


		function nullableSelfType(): ?self
		{
		}


		/** @return B */
		function annotationClassType()
		{
		}


		/** @return B|string */
		function annotationUnionType()
		{
		}


		/** @return String */
		function annotationNativeType()
		{
		}


		/** @return self */
		function annotationSelfType()
		{
		}


		/** @return static */
		function annotationStaticType()
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

	Assert::same('Test\B', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'nullableClassType')));

	Assert::same('string', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'nullableNativeType')));

	Assert::same('NS\A', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'nullableSelfType')));

	Assert::same('Test\B', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationClassType')));

	Assert::same('Test\B', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationUnionType')));

	Assert::same('string', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationNativeType')));

	Assert::same('NS\A', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationSelfType')));

	Assert::same('NS\A', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationStaticType')));

	// class name expanding is NOT supported for global functions
	Assert::same('B', Helpers::getReturnType(new \ReflectionFunction('NS\annotationClassType')));
}
