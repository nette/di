<?php

/**
 * Test: Nette\DI\PhpReflection::expand()
 */

use Nette\DI\PhpReflection,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


/**
 * @inject @var  type
 * @return bool|int */
class TestClass {}


$rc = new ReflectionClass('TestClass');

Assert::same(TRUE, PhpReflection::parseAnnotation($rc, 'inject'));
Assert::same('type', PhpReflection::parseAnnotation($rc, 'var'));
Assert::same('bool|int', PhpReflection::parseAnnotation($rc, 'return'));
