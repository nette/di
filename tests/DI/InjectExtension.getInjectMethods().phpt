<?php

/**
 * Test: Nette\DI\Extensions\InjectExtension::getInjectMethods()
 */

use Nette\DI\Extensions\InjectExtension;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


trait Trait1
{
	function injectT1()
	{}
}

trait Trait2
{
	function injectT2()
	{}
}

class Class1
{
	function inject1()
	{}
}

class Class2 extends Class1
{
	use Trait1;

	function inject2()
	{}
}

class Class3 extends Class2
{
	use Trait2;

	function inject3()
	{}
}

Assert::same([
	'inject1',
], InjectExtension::getInjectMethods('Class1'));

Assert::same([
	'inject1',
	'inject2',
	'injectT1',
], InjectExtension::getInjectMethods('Class2'));

Assert::same([
	'inject1',
	'inject2',
	'injectT1',
	'inject3',
	'injectT2',
], InjectExtension::getInjectMethods('Class3'));
