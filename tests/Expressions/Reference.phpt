<?php declare(strict_types=1);

/**
 * Test: Nette\DI\Expressions\Reference usage.
 */

use Nette\DI\Expressions\Reference;
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


// deprecated alias Nette\DI\Expressions\Reference
Assert::type(Reference::class, new Nette\DI\Expressions\Reference('a'));
Assert::type(Nette\DI\Expressions\Reference::class, new Reference('a'));
