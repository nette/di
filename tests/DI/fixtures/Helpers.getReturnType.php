<?php

namespace NS
{
	use Test\B;

	class A
	{
		public function noType()
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


		/** @return string */
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
