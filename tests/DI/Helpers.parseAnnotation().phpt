<?php

/**
 * Test: Nette\DI\Helpers::expand()
 */

declare(strict_types=1);

use Nette\DI\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


/**
 * @inject @var  type
 * @return bool|int */
class TestClass
{
}

$rc = new ReflectionClass(TestClass::class);

Assert::same('', Helpers::parseAnnotation($rc, 'inject'));
Assert::same(null, Helpers::parseAnnotation($rc, 'injec'));
Assert::same('type', Helpers::parseAnnotation($rc, 'var'));
Assert::same('bool|int', Helpers::parseAnnotation($rc, 'return'));


/** @return*/
class TestClass2
{
}

$rc = new ReflectionClass(TestClass2::class);

Assert::same('', Helpers::parseAnnotation($rc, 'return'));


/** @return
var
 */
class TestClass3
{
}

$rc = new ReflectionClass(TestClass3::class);

Assert::same('', Helpers::parseAnnotation($rc, 'return'));


/**
 * @inject@var
 */
class TestClass4
{
}

$rc = new ReflectionClass(TestClass4::class);

Assert::same(null, Helpers::parseAnnotation($rc, 'inject'));
Assert::same(null, Helpers::parseAnnotation($rc, 'injec'));
Assert::same(null, Helpers::parseAnnotation($rc, 'var'));
