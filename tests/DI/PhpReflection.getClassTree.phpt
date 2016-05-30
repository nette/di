<?php

/**
 * Test: Nette\DI\PhpReflection::getClassTree
 */

use Nette\DI\PhpReflection;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


trait T1 {
}

trait T2 {
	use T1;
}

interface I1 {
}

interface I2 extends I1 {
}

class C1 implements I2
{
	use T2;
}

class C2 extends C1
{
}

Assert::same(['I1'], PhpReflection::getClassTree(new ReflectionClass('I1')));
Assert::same(['I2', 'I1'], PhpReflection::getClassTree(new ReflectionClass('I2')));
Assert::same(['T1'], PhpReflection::getClassTree(new ReflectionClass('T1')));
Assert::same(['T2', 'T1'], PhpReflection::getClassTree(new ReflectionClass('T2')));
Assert::same(['C1', 'I2', 'I1', 'T2', 'T1'], PhpReflection::getClassTree(new ReflectionClass('C1')));
Assert::same(['C2', 'C1', 'I1', 'I2', 'T2', 'T1'], PhpReflection::getClassTree(new ReflectionClass('C2')));
