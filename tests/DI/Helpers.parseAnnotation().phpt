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
 *@return bool|int */
class TestClass
{
}

$rc = new ReflectionClass('TestClass');

Assert::same('', Helpers::parseAnnotation($rc, 'inject'));
Assert::same(null, Helpers::parseAnnotation($rc, 'injec'));
Assert::same('type', Helpers::parseAnnotation($rc, 'var'));
Assert::same('bool|int', Helpers::parseAnnotation($rc, 'return'));


/** @return*/
class TestClass2
{
}

$rc = new ReflectionClass('TestClass2');

Assert::same('', Helpers::parseAnnotation($rc, 'return'));
