<?php

/**
 * Test: Nette\DI\ContainerBuilder::literal()
 */

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::type(Nette\PhpGenerator\PhpLiteral::class, DI\ContainerBuilder::literal(''));
Assert::same('$var', (string) DI\ContainerBuilder::literal('$var'));
Assert::same('$var', (string) DI\ContainerBuilder::literal('$var', null));
Assert::same('$var->?', (string) DI\ContainerBuilder::literal('$var->?'));
Assert::same('$var', (string) DI\ContainerBuilder::literal('$var', []));
Assert::same('$var->item', (string) DI\ContainerBuilder::literal('$var->?', ['item']));
