<?php

/**
 * Test: Nette\DI\Config\Helpers::merge()
 */

use Nette\DI\Config\Helpers;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$obj = new stdClass;
$arr1 = ['a' => 'b', 'x'];
$arr2 = ['c' => 'd', 'y'];


Assert::same(null, Helpers::merge(null, null));
Assert::same(null, Helpers::merge(null, 231));
Assert::same(null, Helpers::merge(null, $obj));
Assert::same([], Helpers::merge(null, []));
Assert::same($arr1, Helpers::merge(null, $arr1));
Assert::same(231, Helpers::merge(231, null));
Assert::same(231, Helpers::merge(231, 231));
Assert::same(231, Helpers::merge(231, $obj));
Assert::same(231, Helpers::merge(231, []));
Assert::same(231, Helpers::merge(231, $arr1));
Assert::same($obj, Helpers::merge($obj, null));
Assert::same($obj, Helpers::merge($obj, 231));
Assert::same($obj, Helpers::merge($obj, $obj));
Assert::same($obj, Helpers::merge($obj, []));
Assert::same($obj, Helpers::merge($obj, $arr1));
Assert::same([], Helpers::merge([], null));
Assert::same([], Helpers::merge([], 231));
Assert::same([], Helpers::merge([], $obj));
Assert::same([], Helpers::merge([], []));
Assert::same($arr1, Helpers::merge([], $arr1));
Assert::same($arr2, Helpers::merge($arr2, null));
Assert::same($arr2, Helpers::merge($arr2, 231));
Assert::same($arr2, Helpers::merge($arr2, $obj));
Assert::same($arr2, Helpers::merge($arr2, []));
Assert::same(['a' => 'b', 'x', 'c' => 'd', 'y'], Helpers::merge($arr2, $arr1));
