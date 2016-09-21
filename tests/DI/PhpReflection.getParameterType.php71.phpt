<?php

/**
 * Test: Nette\DI\PhpReflection::getParameterType
 * @phpversion 7.1
 */

use Nette\DI\PhpReflection;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


use Test\B; // for testing purposes

class A
{
	function method(Undeclared $undeclared, B $b, array $array, callable $callable, $none, ?B $nullable)
	{}
}

$method = new \ReflectionMethod('A', 'method');
$params = $method->getParameters();

Assert::same('Undeclared', PhpReflection::getParameterType($params[0]));
Assert::same('Test\B', PhpReflection::getParameterType($params[1]));
Assert::same('array', PhpReflection::getParameterType($params[2]));
Assert::same('callable', PhpReflection::getParameterType($params[3]));
Assert::null(PhpReflection::getParameterType($params[4]));
Assert::same('Test\B', PhpReflection::getParameterType($params[5]));
