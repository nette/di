<?php

/**
 * Test: Parsing PHP 7 group use statements.
 * @phpVersion 7
 */

use Nette\DI\PhpReflection;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


require __DIR__ . '/files/expandClass.groupUse.php';

Assert::same(
	['A' => 'A\B\A', 'C' => 'A\B\B\C', 'D' => 'A\B\C', 'E' => 'D\E'],
	PhpReflection::getUseStatements(new ReflectionClass('GroupUseTest'))
);
