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
Assert::type(Reference::class, Nette\DI\Expressions\Reference::fromType('stdClass'));


// generateCode()
$builder = new Nette\DI\ContainerBuilder;
$generator = new Nette\DI\PhpGenerator($builder);
Assert::same("\$this->getService('a')", (new Reference('a'))->generateCode($generator));
Assert::same('$service', (new Reference(Reference::Self))->generateCode($generator));
Assert::same('$this', (new Reference(Nette\DI\ContainerBuilder::ThisContainer))->generateCode($generator));
Assert::type(Reference::class, Nette\DI\Expressions\Reference::fromType('stdClass'));


// resolveType()
$defA = $builder->addDefinition('a')->setType(stdClass::class);
$defB = $builder->addDefinition('b')->setCreator(stdClass::class); // type not resolved yet
$resolver = new Nette\DI\Resolver($builder);

Assert::same('stdClass', (new Reference('a'))->resolveType($resolver));
Assert::same('stdClass', (new Reference('b'))->resolveType($resolver)); // triggers resolveDefinition()
Assert::same('Foo\Bar', Reference::fromType('Foo\Bar')->resolveType($resolver));
Assert::null((new Reference(Reference::Self))->resolveType($resolver)); // no current service
Assert::same('stdClass', (new Reference(Reference::Self))->resolveType($resolver->withCurrentService($defA)));
