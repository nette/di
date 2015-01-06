<?php

/**
 * Test: Nette\DI\Helpers::getInjectProperties() with traits
 * @phpversion 5.4 
 */

namespace A
{
	class AInjected
	{

	}
}

namespace B
{
	use A\AInjected;
	
	trait BTrait
	{
		/** @var AInjected @inject */
		public $varA;
	}
}

namespace C
{
	class CClass
	{
		use \B\BTrait;
	}
}

namespace
{
	use Nette\DI\Helpers;
	use Nette\Reflection\ClassType;
	use Tester\Assert;

	require __DIR__ . '/../bootstrap.php';


	$refC = ClassType::from('C\CClass');

	Assert::same( array(
		'varA' => 'A\AInjected',
	), Helpers::getInjectProperties($refC) );
}
