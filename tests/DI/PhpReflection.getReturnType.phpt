<?php

/**
 * Test: Nette\DI\PhpReflection::getReturnType
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

		/** @return String */
		function nativeType()
		{}
	}

	/** @return B */
	function classType()
	{}
}


namespace
{
	use Nette\DI\PhpReflection;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	Assert::null(PhpReflection::getReturnType(new \ReflectionMethod('NS\A', 'noType')));

	Assert::same('Test\B', PhpReflection::getReturnType(new \ReflectionMethod('NS\A', 'annotationSingle')));

	Assert::same('Test\B', PhpReflection::getReturnType(new \ReflectionMethod('NS\A', 'annotationComplex')));

	Assert::same('string', PhpReflection::getReturnType(new \ReflectionMethod('NS\A', 'nativeType')));

	// class name expanding is NOT supported for global functions
	Assert::same('B', PhpReflection::getReturnType(new \ReflectionFunction('NS\classType')));
}
