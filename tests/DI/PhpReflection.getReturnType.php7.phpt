<?php

/**
 * Test: Nette\DI\PhpReflection::getReturnType
 * @phpversion >= 7
 */

namespace NS
{
	use Test\B;

	class A
	{
		function noType()
		{}

		/** @return B */
		function annotationSingle()
		{}

		/** @return B|string */
		function annotationComplex()
		{}

		function nativeType(): string
		{}

		function selfType(): self
		{}
	}

	function classType(): B
	{}
}


namespace
{
	use Nette\DI\PhpReflection;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	Assert::null(PhpReflection::getReturnType(new \ReflectionMethod(NS\A::class, 'noType')));

	Assert::same('Test\B', PhpReflection::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationSingle')));

	Assert::same('Test\B', PhpReflection::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationComplex')));

	Assert::same('string', PhpReflection::getReturnType(new \ReflectionMethod(NS\A::class, 'nativeType')));

	Assert::same('NS\A', PhpReflection::getReturnType(new \ReflectionMethod(NS\A::class, 'selfType')));

	// class name expanding is NOT supported for global functions
	Assert::same('Test\B', PhpReflection::getReturnType(new \ReflectionFunction(NS\classType::class)));
}
