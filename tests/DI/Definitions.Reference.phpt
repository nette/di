<?php

/**
 * Test: Nette\DI\Definitions\Reference usage.
 */

declare(strict_types=1);

use Nette\DI\Definitions\Reference;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$ref = new Reference('a');
Assert::true($ref->isName());
Assert::false($ref->isType());
Assert::false($ref->isSelf());
Assert::same('a', $ref->getValue());


$ref = new Reference('a\b');
Assert::false($ref->isName());
Assert::true($ref->isType());
Assert::false($ref->isSelf());
Assert::same('a\b', $ref->getValue());


$ref = Reference::fromType('a');
Assert::false($ref->isName());
Assert::true($ref->isType());
Assert::false($ref->isSelf());
Assert::same('\a', $ref->getValue());


$ref = new Reference(Reference::Self);
Assert::false($ref->isName());
Assert::false($ref->isType());
Assert::true($ref->isSelf());
Assert::same(Reference::Self, $ref->getValue());
