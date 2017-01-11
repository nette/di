<?php

/**
 * Test: Nette\DI\ContainerBuilder::literal()
 */

declare(strict_types=1);

use Nette\DI;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


Assert::type(Nette\PhpGenerator\PhpLiteral::class, DI\ContainerBuilder::literal(''));
Assert::same('$var', (string) DI\ContainerBuilder::literal('$var'));
Assert::same('$var', (string) DI\ContainerBuilder::literal('$var', NULL));
Assert::same('$var->?', (string) DI\ContainerBuilder::literal('$var->?'));
Assert::same('$var', (string) DI\ContainerBuilder::literal('$var', []));
Assert::same('$var->item', (string) DI\ContainerBuilder::literal('$var->?', ['item']));
