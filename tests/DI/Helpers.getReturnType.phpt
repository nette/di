<?php

/**
 * Test: Nette\DI\Helpers::getReturnType
 */

namespace NS
{
	use Test\B;

	class A
	{
		function noType()
		{}

		/** @return B */
		function annotationClassType()
		{}

		/** @return B|string */
		function annotationUnionType()
		{}

		/** @return String */
		function annotationNativeType()
		{}

		/** @return self */
		function annotationSelfType()
		{}

		/** @return static */
		function annotationStaticType()
		{}
	}

	/** @return B */
	function annotationClassType()
	{}
}


namespace
{
	use Nette\DI\Helpers;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	Assert::null(Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'noType')));

	Assert::same('Test\B', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationClassType')));

	Assert::same('Test\B', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationUnionType')));

	Assert::same('string', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationNativeType')));

	Assert::same('NS\A', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationSelfType')));

	Assert::same('NS\A', Helpers::getReturnType(new \ReflectionMethod(NS\A::class, 'annotationStaticType')));

	// class name expanding is NOT supported for global functions
	Assert::same('B', Helpers::getReturnType(new \ReflectionFunction('NS\annotationClassType')));
}
