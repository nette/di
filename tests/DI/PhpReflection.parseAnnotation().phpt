<?php

/**
 * Test: Nette\DI\PhpReflection::expand()
 */

use Nette\DI\PhpReflection;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


/**
 * @inject @var  type
 *@return bool|int */
class TestClass {}

$rc = new ReflectionClass('TestClass');

Assert::same('', PhpReflection::parseAnnotation($rc, 'inject'));
Assert::same(NULL, PhpReflection::parseAnnotation($rc, 'injec'));
Assert::same('type', PhpReflection::parseAnnotation($rc, 'var'));
Assert::same('bool|int', PhpReflection::parseAnnotation($rc, 'return'));


/** @return*/
class TestClass2 {}

$rc = new ReflectionClass('TestClass2');

Assert::same('', PhpReflection::parseAnnotation($rc, 'return'));
